<?php

namespace App\Transformers;

use App\Http\Controllers\Users;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Facades\Log;
use League\Fractal\TransformerAbstract;

class UserDetailedTransformer extends TransformerAbstract
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
        $my = auth()->user();
        //Log::debug('my user: ' . print_r($my,true));
        //Log::debug('user: ' . print_r($user,true));
        $session = $user->session();
        if ( $user->user_id !== $my->user_id) {
            $user = User::find($user->user_id);
            $session = null;
        }




        return [
            '_$dbName'                      => null,
            'id'                            => (int) $user->user_id,
            'name'                          => (string) $user->name,
//            'password'                      => (string) $user->password,
            'email'                         => (string) $user->email,
//            'created'                       => (string) $user->creation_date,
//            'createdBy'                     => (int) $user->created_by_id,
//            'modified'                      => (string) $user->modification_date,
//            'modifiedBy'                    => (int) $user->modified_by_id,
            'isActive'                      => (bool) $user->is_active,
            'isDuplicate'                   => (bool) $user->isDuplicate,
            'session'                       => $session,
            'roles'                         => $user->roles(),
            'rolesConcat'                   => $user->rolesConcat(),
            'brands'                        => $user->brands(),
            'liveStreamBrands'              => $user->liveStreamBrands(),
            'liveStreamBrandsConcat'        => $user->liveStreamBrandsConcat(),
            'vodBrands'                     => $user->vodBrands(),
            'vodBrandsConcat'               => $user->vodBrandsConcat(),
            'groups'                        => $user->groups(),
            'liveStreamGroups'              => $user->liveStreamGroups(),
            'liveStreamGroupsConcat'        => $user->liveStreamGroupsConcat(),
            'vodGroups'                     => $user->vodGroups(),
            'vodGroupsConcat'               => $user->vodGroupsConcat(),
//            'providers'                     => $user->providers(),
            'providers' => [],
            'liveStreamProviders'           => $user->liveStreamProviders(),
            'vodProviders'                  => $user->vodProviders(),
            'nowtv'                         => $user->nowtv(),
            'productOptions'                => $user->productOptions(),
        ];
    }
}
