<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

/** Additional fields exposed by the core feed editor, beyond definition defaults. */
class SupportedPaths
{
    public const EDITOR = [
        'bundle_associated_products_mode',
        'bundle_combined_weight',
        'categories_include_all_products',
        'categories_inventory_source_map',
        'categories_locale',
        'categories_provider_taxonomy_by_category',
        'categories_sort_mode',
        'columns_product_columns',
        'configurable_add_out_of_stock',
        'configurable_associated_products_link_add_unique',
        'configurable_associated_products_mode',
        'configurable_attribute_merge_value_separator',
        'configurable_inherit_parent_out_of_stock',
        'configurable_map_inherit',
        'filters_add_out_of_stock',
        'filters_adwords_price_buckets',
        'filters_attribute_sets',
        'filters_find_and_replace',
        'filters_map_replace_empty_columns',
        'filters_output_limit',
        'filters_product_types',
        'filters_skip_column_empty',
        'filters_skip_price_above',
        'filters_skip_price_below',
        'general_apply_catalog_price_rules',
        'general_complex_duplicates_check',
        'general_currency',
        'general_feed_dir',
        'general_stock_attribute_code',
        'general_use_default_stock',
        'general_use_qty_increments',
        'general_use_stock_reservations',
        'grouped_add_out_of_stock',
        'grouped_associated_products_link_add_unique',
        'grouped_associated_products_mode',
        'grouped_map_inherit',
        'grouped_price_display_mode',
        'options_mode',
        'options_vary_categories',
        'output_params_encoding',
        'promotions_enabled',
        'promotions_provider_widget',
        'shipping_add_tax_to_price',
        'shipping_country',
        'shipping_methods',
        'shipping_only_free_shipping',
        'shipping_only_minimum',
        'shipping_weight_column'
    ];
}
