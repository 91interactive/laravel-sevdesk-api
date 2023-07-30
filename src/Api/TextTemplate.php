<?php
/*
 * TextTemplate.php
 * @author Ruben Müller <mueller@91interactive.com>
 * @copyright 2023 Ruben Müller/
 */

namespace Exlo89\LaravelSevdeskApi\Api;

use Exlo89\LaravelSevdeskApi\Api\Utils\ApiClient;
use Exlo89\LaravelSevdeskApi\Api\Utils\Routes;
use Illuminate\Support\Collection;


class TextTemplate extends ApiClient
{
    // =========================== all ====================================

    /**
     * Return all countries.
     *
     * @return mixed
     */
    public function all(int $limit = 1000)
    {
        return Collection::make($this->_get(Routes::TEXT_TEMPLATE, ['limit' => $limit]));
    }

    // =========================== get ====================================

    /**
     * Return a single text template.
     *
     * @param $category
     * @param $objectType
     * @param $textType
     * @return mixed
     */
    public function get($category, $objectType, $textType)
    {
        return $this->_get(Routes::TEXT_TEMPLATE,["category"=>$category, "objectType"=>$objectType,"textType"=>$textType])['objects'];
    }
}
