<?php

namespace Andyabih\LaravelFakeApi;

use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LaravelFakeApi {
    /**
     * Faker factory instance.
     * 
     * @var \Faker\Factory
     */
    protected $faker;

    /**
     * List of configured endpoints.
     * 
     * @var array
     */
    protected $endpoints;

    /**
     * Laravel request object.
     * 
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * User specified preset endpoints
     * 
     * @var array
     */
    protected $presetResponses;

    /**
     * Error string to return.
     * 
     * @var mixed
     */
    protected $error = false;

    /**
     * Selected endpoint.
     * 
     * @var string
     */
    protected $endpoint;

    /**
     * Selected settings.
     * 
     * @var array
     */
    protected $settings;

    /**
     * Create a new LFA instance.
     * 
     * @return void
     */
    public function __construct() {
        $this->faker           = Faker::create();
        $this->endpoints       = config('laravel-fake-api.endpoints');
        $this->presetResponses = $this->getPresetResponses();
    }

    /**
     * Generate a response for an endpoint.
     * 
     * @param string $endpoint
     * @param mixed $key
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    public function generate($endpoint, $key, $request) {
        if(! $this->isValidEndpoint($endpoint)) return abort(404);

        $this->request  = $request;
        $this->endpoint = $endpoint;
        $this->settings = $this->getSettings($endpoint);

        if($this->isSingular($endpoint) || isset($key)) {
            $response = $this->makeOne($endpoint, null, $key);
        } else {
            $response = $this->makeMany($endpoint, $this->request->input('_count'));

            if($this->hasPagination($endpoint)) {
                $response = collect($response)->paginate($this->settings['paginate']);
            }
        }

        if($this->error) {
            return $this->error;
        }

        return [$response, 200];
    }  

    /**
     * Create and return many endpoint responses.
     * 
     * @param string $endpoint
     * @param string $count
     * @return array
     */
    protected function makeMany($endpoint, $count = NULL) {
        $count     = (isset($count) && $count > 0) ? $count : rand(2, 12);
        $responses = [];

        for($i = 0; $i < $count; $i++) {
            $responses[] = $this->makeOne($endpoint, $i);
        }

        $responses = $this->filterFromRequest($responses);
        return $responses;
    }

    /**
     * Create and return only one endpoint instance.
     * 
     * @param string $endpoint
     * @param mixed $index
     * @param mixed $identifiable
     * @return mixed
     */
    protected function makeOne($endpoint, $index = NULL, $identifiable = NULL) {
        [$fields, $settings] = $this->getEndpoint($endpoint);
        $responseArray    = [];

        if(! $this->checkAuth($settings)) {
            $this->error = [['message' => "Unauthorized."], 401];
            return false;
        }

        foreach($fields as $key => $options) {
            if($field = $this->makeField($endpoint, $key, $options, $index)) {
                $responseArray[$key] = $field;
            }
        }
        
        if(isset($identifiable)) {
            if($response = $this->getPresetResponse($endpoint, $identifiable, $settings)) {
                $responseArray = $response;
            } else {
                $responseArray = $this->replaceIdentifiable($responseArray, $identifiable, $settings);
            }
        }

        if($this->endpoint == $endpoint) {
            if($this->request->get("_only")) {
                $onlyFields    = explode(',', $this->request->get("_only"));
                $responseArray = array_filter($responseArray, function($key) use($onlyFields) {
                    return in_array($key, $onlyFields);
                }, ARRAY_FILTER_USE_KEY);
            }
            
            if($this->request->get("_without")) {
                $withoutFields = explode(',', $this->request->get("_without"));
                $responseArray = array_filter($responseArray, function($key) use($withoutFields) {
                    return ! in_array($key, $withoutFields);
                }, ARRAY_FILTER_USE_KEY);
            }
        }
        
        return $responseArray;
    }

    /**
     * Create a new field.
     * 
     * @param string $endpoint
     * @param string $key
     * @param string $options
     * @param mixed $index
     * @return array
     */
    protected function makeField($endpoint, $key, $options, $index = NULL) {
        if(isset($index) && 
            $presetField = $this->getPresetField($endpoint, $key, $index)) {
            return $presetField;
        }

        $explodedOptions = explode("|", $options);
        $type            = head($explodedOptions);
        $fakerMethod     = Str::camel($type);

        if($this->isReserved($type)) {
            if($this->request->input('_no_relationships')) {
                return false;
            }

            return $this->makeRelationship($key, $explodedOptions);
        }
        
        $optionParameters = array_slice($explodedOptions, 1);
        $parameters = array_map(function($parameter) {
            return is_numeric($parameter) ? $parameter : '"' . $parameter . '"';
        }, $optionParameters);
        $arguments = !blank($optionParameters) ? implode(", ", $parameters) : '';

        $fakerValue = !blank($arguments) ? 
            $this->faker->$fakerMethod($arguments) :
            $this->faker->$fakerMethod();

        return $fakerValue;
    }

    /**
     * Make a relationship entry in the response.
     * 
     * @param string $key
     * @param array $options
     * @return array
     */
    protected function makeRelationship($key, $options) {
        $type           = head($options);
        $endpointName   = substr($type, 1);
        $count          = array_slice($options, 1);
        $countParameter = !blank($count) ? $count[0] : NULL;

        // If the user supplied a plural key (ie: users), then return multiple instances, else return one.
        $response = $this->isSingular($key) ? 
            $this->makeOne($endpointName, 0) :
            $this->makeMany($endpointName, $countParameter);
        
        return $response;
    }

    /**
     * Check if the user supplied any filters and apply them.
     * 
     * @param array $responses
     * @return array
     */
    protected function filterFromRequest($responses) {
        $responses = array_filter($responses, function($response) {
            foreach($this->request->all() as $key => $requestParameter) {
                if($this->isReserved($key)) continue;

                if(Str::contains($key, '__')) {
                    $explodedKey = explode('__', $key);
                    try {
                        if($response[$explodedKey[0]][$explodedKey[1]] != $requestParameter)  return false;
                    } catch(\Exception $e) {}
                } else {
                    if(! in_array($key, array_keys($response))) continue;
                    if($response[$key] != $requestParameter) {
                        return false;
                    }
                }
            }
            return true;
        });

        return array_values($responses);
    }
    
    /**
     * Check if the given endpoints requires pagination.
     * 
     * @param string $endpoint
     * @return bool
     */
    protected function hasPagination() {
        return (isset($this->settings['paginate']) && $this->settings['paginate']);
    }
    
    /**
     * Check if the user supplied a bearer token.
     * 
     * @param array $settings
     * @return bool
     */
    protected function checkAuth($settings) {
        if(isset($settings['auth']) && $settings['auth']) {
            if($this->request->header('Authorization')) {
                return true;
            } 

            return false;
        }

        return true;
    }

    /**
     * Load preset field.
     * 
     * @param string $endpoint
     * @param string $key
     * @param int $index
     * @return mixed
     */
    protected function getPresetField($endpoint, $key, $index) {
        if(isset($this->presetResponses[$endpoint]) && isset($this->presetResponses[$endpoint][$index])) {
            $selectedResponse = $this->presetResponses[$endpoint][$index];

            if(isset($selectedResponse[$key])) return $selectedResponse[$key];
        }

        return false;
    }
    
    /**
     * Replace identifiable column with searched value.
     * 
     * @param array $responseArray
     * @param string $identifiable
     * @param array $settings
     * @return array
     */
    protected function replaceIdentifiable($responseArray, $identifiable, $settings) {
        $identifiedBy = $settings['identifiable'] ?? 'id';

        if(isset($responseArray[$identifiedBy])) {
            $responseArray[$identifiedBy] = $identifiable;
        }

        return $responseArray;
    }

    /**
     * Return fields and settings from an endpoint.
     * 
     * @param string $endpoint
     * @return array
     */
    protected function getEndpoint($endpoint) {
        $selectedEndpoint = $this->endpoints[$endpoint];
        $settings         = $selectedEndpoint['_settings'] ?? [];
        $fields           = array_diff_key($selectedEndpoint, array_flip(['_settings']));
        
        return [$fields, $settings];
    }

    /**
     * Load user supplied preset endpoint responses.
     * 
     * @return array
     */
    protected function getPresetResponses() {
        return json_decode(
            file_get_contents(base_path() . '/laravel-fake-api.json'),
            true
        );
    }

    /**
     * Load a preset identified response.
     * 
     * @param string $endpoint
     * @param string $identifiable
     * @param array $settings
     */
    protected function getPresetResponse($endpoint, $identifiable, $settings) {
        $identifiedBy = $settings['identifiable'] ?? 'id';

        if(isset($this->presetResponses[$endpoint])) {
            foreach($this->presetResponses[$endpoint] as $response) {
                if($response[$identifiedBy] == $identifiable) return $response;
            }
        }

        return false;
    }

    /**
     * Return settings columns for endpoint.
     * 
     * @var string $endpoint
     * @return array
     */
    protected function getSettings($endpoint) {
        $endpoint = $this->endpoints[$endpoint];

        return $endpoint['_settings'] ?? [];
    }

    /**
     * Check whether the supplied string is singular.
     * 
     * @param string $name
     * @return bool
     */
    protected function isSingular($name) {
        return Str::plural($name) != $name;
    }

    /**
     * Check whether the key is a reserved key (starts with _).
     * 
     * @param string $type
     * @return bool
     */
    protected function isReserved($type) {
        return Str::startsWith($type, '_');
    }
    
    /**
     * Check whether the user is supplying a valid endpoint.
     * 
     * @param string $endpoint
     * @return bool
     */
    protected function isValidEndpoint($endpoint) {
        return in_array($endpoint, array_keys($this->endpoints));
    }
}