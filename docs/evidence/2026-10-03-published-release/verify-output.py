from pathlib import Path
import json,csv,re,hashlib
root=Path(__file__).parent
comparison=json.loads((root/'comparison.json').read_text())
checks=[]
def check(ok,name):
 assert ok,name
 checks.append(name)
def rows(path):
 with path.open(encoding='utf-8-sig',newline='') as f:
  r=csv.DictReader(f,delimiter='\t'); result=list(r)
  check(all(None not in row and all(v is not None for v in row.values()) for row in result),'valid row widths: '+path.name)
  return result
for id in ['801','802','804','805','806','807']:
 result=comparison[id]
 expected_changes={('|3','availability'),('|4','availability')}
 if id in ['804','805']:expected_changes={('|migration-size-large','availability'),('|migration-size-small','availability')}
 if id in ['802','806']:expected_changes|={(f'|{i}','identifier_exists') for i in range(1,10)}
 if id=='807':expected_changes=set()
 check({(v['id'],v['column']) for v in result['changed_values']}==expected_changes,'only reviewed field changes: '+id)
 for v in result['changed_values']:
  expected=('out_of_stock','in_stock') if v['column']=='availability' else ('FALSE','')
  check((v['legacy'],v['destination'])==expected,'reviewed value transition: '+id+' '+v['id']+' '+v['column'])
 check(result['missing_rows']==result['added_rows']==[],'same products: '+id)
 if id in ['802','806']:
  check(set(result['missing_columns'])=={'promotions_id'},'Google singular promotion header: '+id)
  check(set(result['added_columns'])=={'promotion_id','item_group_title','variant_option','color','size','material','pattern','gender','age_group'},'Google variant headers: '+id)
 else:check(result['missing_columns']==result['added_columns']==[],'same columns: '+id)
check(comparison['807']['byte_equal'],'simple Generic sample matches bytes')
inv=comparison['803']
check(inv['missing_rows']==['default|2','default|8','default|9'] and inv['added_rows']==['default|5'],'MSI rows follow source assignments and configurable children')
check(inv['changed_values']==[],'all common Local Inventory row values match')
catalog=json.loads((root/'catalog-expectations.json').read_text())
check(all(p['salable'] for p in catalog if p['entity_id'] in ['3','4','5']),'Magento confirms variant and parent salability')
check(all(p['sources']==[] for p in catalog if p['entity_id'] in ['2','8','9']),'omitted inventory products had no source assignment')
prices={'1':'29.95 USD','2':'15.00 USD','3':'19.00 USD','4':'24.00 USD','5':'19.00 USD','6':'12.00 USD','7':'17.00 USD','8':'29.00 USD','9':'12.00 USD'}
for id in [801,802,806]:
 actual={r['id']:r for r in rows(root/f'destination-{id}.tsv')}
 check({k:r['price'] for k,r in actual.items()}==prices,'independent product and complex prices: '+str(id))
 check(actual['1']['sale_price']=='19.95 USD','sale price: '+str(id))
 check(actual['1']['title']=='Café "Trail" & Camp Shirt','Unicode and quoted title: '+str(id))
 check(actual['2']['availability']=='out_of_stock','out-of-stock Shopping row: '+str(id))
 if id in [802,806]:
  check({k:(r['item_group_title'],r['variant_option']) for k,r in actual.items() if r['variant_option']}=={'3':('Migration Configurable','migration_size:Large'),'4':('Migration Configurable','migration_size:Small')},'variant titles and options: '+str(id))
  check(all(r[k]=='' for r in actual.values() for k in ['color','size','material','pattern','gender','age_group']),'unconfigured variant attributes remain empty: '+str(id))
 if id==806:check(all(re.fullmatch(r'\d+(?:\.\d+)? kg',r['shipping_weight']) for r in actual.values()),'explicit Google weight units on every row')
old=rows(root/'inventory-zero-legacy.tsv');new=rows(root/'inventory-zero-destination.tsv')
a=next(r for r in old if r['id']=='2');b=next(r for r in new if r['id']=='2')
check(a==b and b['quantity']=='0' and b['availability']=='out_of_stock' and b['price']=='15.00 USD','assigned zero-stock inventory row preserved exactly')
check(len(old)==8 and len(new)==7,'assigned source inventory counts')
summary={'checks_passed':len(checks),'checks':checks,'unexpected_output_differences':0,'simple_generic_byte_equal':True,'migration_field_changes_fixed':['null shipping weight units'],'reviewed_release_changes':['variant availability follows Magento salability','Local Inventory uses MSI source assignment and configurable child sources','Google promotion_id and eight variant columns','Google identifier_exists no longer infers FALSE from missing identifier data'],'remaining_limits':['Inherited null weight units on legacy complex parents remain incomplete in the default feed; configured sample 806 explicitly uses kg.','Local Inventory rows and Google columns are not byte-equivalent.','No external recipient acceptance; all products, URLs and prices are synthetic.']}
(root/'output-verification.json').write_text(json.dumps(summary,indent=2)+'\n')
print(f'PASS: {len(checks)} output assertions; no unexplained differences; Generic sample byte-identical.')
