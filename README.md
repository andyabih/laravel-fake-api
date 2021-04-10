# Laravel Fake API
Create placeholder API endpoints from a simple PHP array. 

LFA utilizes [Faker](https://github.com/fzaninotto/Faker) for dummy data.

Inspired by [JSON Server](https://github.com/typicode/json-server).

# Installation
To install LFA, run the following composer command:
```
composer require andyabih/laravel-fake-api --dev
```
Next, publish the config file to fill in your endpoints & responses:
```
php artisan vendor:publish --provider="Andyabih\LaravelFakeApi\LaravelFakeApiServiceProvider" --tag="config"
```


# Configuration
Below is a sample `laravel-fake-api.php` config file:
```php
<?php

return [
    'base_endpoint' => '/api/fake',

    'endpoints' => [
        'posts' => [
            '_settings' => [
                'identifiable' => 'slug',
                'auth'         => true,
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
```

## Base endpoint
LFA registers the `/api/fake/` prefix for all endpoints, you can change that by changing the `base_endpoint` entry in the configuration file.

## Endpoints
Inside the `endpoints` array, you can create all your different endpoints. The example above contains two: `/posts` and `/categories` (`/api/fake/posts` for full).

## Fields
For each endpoint, you can then specify all the fields you want the response to contain. We've defined 5 here: `id`, `title`, `slug`, `text`, and `category`. The values for these fields are [Faker](https://github.com/fzaninotto/Faker) methods. Make sure you snake case them (ie: `randomDigit` becomes `random_digit`), and any additional argument you want to pass should be separated with a pipe; so `"text" => 'paragraph|2'` translates to `paragraph(2)`.

In case you want to show a foreign entity inside the endpoint response, you can prefix it with an underscore. A plural key will return an array of multiple entities, and a singular one would return only one (ie: `"categories" => "_categories"` will return an array of categories, but `"category" => "_category"` will only return a single entry).

If you are requesting multiple relationship entities, you can also pass in an optional argument specifying the amount of results you want, so you can do something like `"categories" => "_categories|5"` which will return `5` categories.

## Endpoint settings
A reserved `_setting` key is used to specify any additional settings to the endpoint. Currently, only 3 settings are available: `identifiable`, `paginate`, and `auth`.

### Identifiable
The identifiable option will determine what column name is used to identify specific entries. The default value for the `identifiable` setting is `id`, meaning a GET call to `/api/fake/categories/1` will check against the `id` field. In the above example, users are identified by their slug, so you will need to access them using something like `/api/fake/users/user-slug-here`.

### Paginate
Straightforward here. If you are expecting multiple results, you can paginate the response by enabling the `paginate` option (which is by default `false`), and specify the amount of entries you want per page (so `5` in the example).

### Auth
LFA also offers a 'fake' authentication layer. If enabled (it's `false` by default), you will receive a 401 unauthorized error if you do not call the endpoint with an `Authorization` header. No further checks are done on the token, it just checks if the header exists.

# Filters
## _count
You can pass in the query parameter `_count` to specify the number of results you want. Calling `/api/fake/categories?_count=5` will return `5` categories.

## _without
You can use the `_without` parameter to specify which columns you want to exclude. `/api/fake/posts?_without=title` will return posts without the title field.

## _only
Same logic as `_without`, but this time you specify which columns you want to include. `/api/fake/posts?_only=title` will only return the title field for the posts.

## _no_relationships
You can specify that you want to ignore all embedded relationships with the `_no_relationships` parameter. `/api/fake/posts?_no_relationships=1` will not return the `category` entity inside the response.

## Column name
You can also pass in a column name with a value to filter by value. `/api/fake/posts?slug=slug-1` will only return entries where the `slug` field is equal to `slug-1`. Also works with relationships, so you can do something like `/api/fake/posts?categories__id=1`, the format for this is `entityName__fieldName`.

# Preset responses
LFA checks for a `laravel-fake-api.json` file in the root of your Laravel project. If available, LFA will combine both the randomized dummy data & the preset responses in your JSON file.

A sample JSON file for the above configuration could be something like:
```json
{
    "categories": [
        {
            "id": 1,
            "name": "Category 1",
            "image": "https://google.com/image.jpg"
        },
        {
            "id": 2,
            "name": "Category 2",
            "image": "https://google.com/image.jpg"
        },
        {
            "id": 3,
            "name": "Category 3",
            "image": "https://google.com/image.jpg"
        }
    ]
}
```