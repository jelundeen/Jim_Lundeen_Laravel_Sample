<?php

namespace App\Transformers;

use App\Models\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    /**
     * List of resources to automatically include
     *
     * @var array
     */
    protected array $defaultIncludes = [
    ];

    /**
     * List of resources possible to include
     *
     * @var array
     */
    protected array $availableIncludes = [
    ];

    /**
     * A Fractal transformer.
     *
     * @return array
     */
    public function transform(User $user): array
    {
        return [
            'name' => (string)$user->name,
            'roles' => $user->rolesConcat(),
            'token' => session()->getId(),
            'brands' => $user->brands(),
            'liveStreamBrands' => $user->liveStreamBrands(),
            'liveStreamBrandsConcat' => $user->liveStreamBrandsConcat(),
            'vodBrands' => $user->vodBrands(),
            'vodBrandsConcat' => $user->vodBrandsConcat(),
            'nowtv' => $user->nowtv(),
            'productOptions' => $user->productOptions(),
        ];
    }
}
