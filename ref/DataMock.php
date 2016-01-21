<?php

/**
 * @var array
 */
$testWebsiteSchema = array(
    // Default Website
    'website_one' => array(
        'code'       => 'website_one',
        'name'       => 'Website One',
        'sort_order' => '0',
        '_groups' => array(
            'Group One' => array(
                'name' => 'Group One',
                '_root_category' => array(
                    'url_key' => 'category_root_one',
                    'name' => 'Category Root One'
                ),
                '_stores' => array(
                    'one_store_one' => array(
                        'code' => 'one_store_one',
                        'name' => 'One Store One',
                    ),
                    'one_store_two' => array(
                        'code' => 'one_store_two',
                        'name' => 'One Store Two',
                        '_is_default' => true,
                    ),
                ),
            ),
            'Group Two' => array(
                'name' => 'Group Two',
                '_root_category' => array(
                    'url_key' => 'category_root_two',
                    'name' => 'Category Root Two'
                ),
                '_stores' => array(
                    'two_store_one' => array(
                        'code' => 'two_store_one',
                        'name' => 'Two Store One',
                    ),
                    'two_store_two' => array(
                        'code' => 'two_store_two',
                        'name' => 'Two Store Two',
                        '_is_default' => true,
                    ),
                ),
            ),
        ),
    ),
);