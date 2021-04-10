
<?php

return [
    'base_endpoint' => '/api/fake',

    'endpoints' => [
        'posts' => [
            '_settings' => [
                'identifiable' => 'slug',
                'auth'         => false,
                'paginate'     => 5,
            ],
            
            'id'       => 'random_digit_not_null',
            'title'    => 'word',
            'slug'     => 'word',
            'text'     => 'paragraph|2',
            'category' => '_categories'
        ],

        'categories' => [
            'id'    => 'random_digit_not_null',
            'name'  => 'word',
            'image' => 'image_url'
        ],
    ]
];