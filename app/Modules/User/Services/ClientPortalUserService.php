<?php

namespace App\Modules\User\Services;

use abeautifulsite\SimpleImage;
use App\Core\User;
use App\Modules\File\Services\FileService;
use App\Modules\Person\Models\Person;
use App\Modules\Person\Repositories\LinkPersonCompanyRepository;
use Illuminate\Support\Facades\DB;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Finder\SplFileInfo;

class ClientPortalUserService
{
    /**
     * @var Container
     */
    private $app;

    /**
     * @var Client
     */
    private $client;

    /**
     * Initialize class
     *
     * @param Container $app
     */
    public function __construct(
        Container $app
    ) {
        $this->app = $app;
    }

    /**
     * Create a new user
     *
     * @param $companyPersonId
     *
     * @return int
     * @throws Exception
     */
    public function createPerson($companyPersonId)
    {
        $company = $this->getCompany($companyPersonId);

        /** @var Person|EloquentBuilder $personModel */
        $personModel = $this->app[Person::class];

        $person = $personModel->newInstance();

        $person->custom_1 = 'Client Portal User';
        $person->custom_3 = $company->custom_1;
        $person->custom_8 = config('app.crm_user') != 'bfc' ? $companyPersonId : '';
        $person->status_type_id = getTypeIdByKey('person_status.active');
        $person->type_id = getTypeIdByKey('person.contact');
        $person->save();

        if (config('app.crm_user') == 'bfc') {
            /** @var LinkPersonCompanyRepository $linkPersonRepository */
            $linkPersonRepository = $this->app[LinkPersonCompanyRepository::class];
            $linkPersonRepository->linkUnlinkClientPortalUser($companyPersonId, $person->person_id, 1);
        }
        
        if ($company->custom_8 != $companyPersonId) {
            $company->custom_8 = $companyPersonId;
            $company->save();
        }

        return $person->person_id;
    }

    public function setClientPortalCompanyPersonId($clientId)
    {
        /** @var User|Builder $user */
        $user = new User();
        $user = $user->where('person_id', '=', $clientId)->first();
        if ($user != null && $user->company_person_id != $user->person_id) {
            $user->company_person_id = $user->person_id;
            $user->save();
        }
    }


    /**
     * Return the list of client portal customer
     *
     * @param bool $reportLinkAdmin
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function listCustomers($reportLinkAdmin = false)
    {
        if (config('app.crm_user') == 'bfc') {
            /** @var User|Builder $model */
            $model = $this->app[User::class];
            $model = $model->whereNotNull('users.company_person_id')
                ->join('person as portalPerson', 'portalPerson.person_id', '=', 'users.person_id')
                ->join('person as companyPerson', 'companyPerson.person_id', '=', 'users.company_person_id')
                ->leftJoin('link_person_company as linkPersonCompany', 'linkPersonCompany.person_id', '=', 'portalPerson.person_id')
                ->whereIn('portalPerson.status_type_id', [getTypeIdByKey('company_status.active'), getTypeIdByKey('person_status.active')])
                ->where('portalPerson.type_id', '=', getTypeIdByKey('person.contact'))
                ->selectRaw(implode(',', [
                    'portalPerson.person_id as person_id',
                    'portalPerson.custom_3 as company_name',
                    'companyPerson.person_id as company_id',
                    'linkPersonCompany.member_person_id as client_member_id'
                ]))
                ->orderBy('portalPerson.custom_3');
        } else {
            /** @var Builder|Person $model */
            $model = $this->app[Person::class];
            $model = $model
                ->join('person as portalPerson', 'portalPerson.custom_8', '=', 'person.person_id')
                ->join('type as personType', 'personType.type_id', '=', 'portalPerson.type_id')
                ->join('type as personStatusType', 'personStatusType.type_id', '=', 'portalPerson.status_type_id')
                ->leftJoin(
                    'link_person_company as linkPersonCompany',
                    'linkPersonCompany.person_id',
                    '=',
                    'person.person_id'
                )
                ->where('personType.type_key', '=', 'person.contact')
                ->where('portalPerson.custom_1', '=', 'Client Portal User')
                ->where('personStatusType.type_value', '=', 'active')
                ->selectRaw(implode(',', [
                    'person.person_id as person_id',
                    'person.custom_1 as company_name',
                    'portalPerson.person_id as company_id',
                    'linkPersonCompany.member_person_id as client_member_id',
                ]))
                ->distinct()
                ->orderBy('person.custom_1');
        }
        
        return $model->get();
    }

    /**
     * Return the list of client portal user
     *
     * @param null $search
     *
     * @return mixed
     *
     */
    public function listPermissions($search = null)
    {
        if (config('app.crm_user') == 'bfc') {
            /** @var User|Builder $model */
            $model = $this->app[User::class];

            $model = $model->whereNotNull('users.company_person_id')
                ->join('person as portalPerson', 'portalPerson.person_id', '=', 'users.person_id')
                ->join('type', 'portalPerson.status_type_id', '=', 'type.type_id')
                ->leftJoin('link_person_company as linkPersonCompany', 'linkPersonCompany.person_id', '=', 'portalPerson.person_id')
                ->whereIn('portalPerson.status_type_id', [getTypeIdByKey('company_status.active'), getTypeIdByKey('person_status.active')])
                ->where('portalPerson.type_id', '=', getTypeIdByKey('person.contact'))
                ->selectRaw(implode(',', [
                    'portalPerson.person_id as person_id',
                    'portalPerson.custom_3 as company_name',
                    'users.email as login',
                    'type.type_value as status',
                ]));

            if ($search != null) {
                $model->where('users.email', 'LIKE', '%'.$search.'%')
                    ->orWhere('portalPerson.custom_3', 'LIKE', '%'.$search.'%');
            }
        } else {
            /** @var Builder|Person $model */
            $model = $this->app[Person::class];
            $model = $model
                ->join('person as portalPerson', 'portalPerson.custom_8', '=', 'person.person_id')
                ->join('type as personType', 'personType.type_id', '=', 'portalPerson.type_id')
                ->join('type as personStatusType', 'personStatusType.type_id', '=', 'portalPerson.status_type_id')
                ->join('users as portalUser', 'portalUser.company_person_id', '=', 'person.person_id')
                ->where('personType.type_key', '=', 'person.contact')
                ->where('portalPerson.custom_1', '=', 'Client Portal User')
                ->selectRaw(implode(',', [
                    'person.person_id as person_id',
                    'person.custom_1 as company_name',
                    'portalUser.email as login',
                    'personStatusType.type_value as status',
                ]))
                ->distinct();
        }
        
        return $model->get();
    }

    /**
     * @param $personId
     *
     * @return mixed
     */
    public function getSettings($personId)
    {
        /** @var Builder $settingTable */
        $settingTable = DB::table('client_portal_company_settings');
        $settingsData = $settingTable
            ->where('company_person_id', '=', $personId)
            ->first();

        return $settingsData;
    }

    /**
     * Store logo image
     *
     * @param $logo
     * @param $personId
     *
     * @return array
     *
     * @throws Exception
     * @throws RuntimeException
     */
    public function storeLogoImage($logo, $personId)
    {
        $this->client = guzzleClient();
        $clientPortalUrl = config('client_portal.url');
        $company = config('app.crm_user');

        if (empty($clientPortalUrl)) {
            throw new RuntimeException('Client portal url not set!');
        }

        // resize file
        $resizeImagePath = $this->resizeImage($logo, $personId, $company);

        // get its base64
        $content = file_get_contents($resizeImagePath);

        // unlink file
        unlink($resizeImagePath);

        $fileService = $this->app->make(FileService::class);
        $typeId = getTypeIdByKey('file.client_portal_logo');

        $file = $fileService->getFirstByCriteria([
            'person_id'  => $personId,
            'table_name' => 'person',
            'table_id'   => $personId,
            'type_id'    => $typeId,
        ]);

        if ($file) {
            $fileService->updateFromContent(
                $file->getId(),
                $content,
                "client_portal_logo_$personId",
                '',
                'person',
                $personId,
                null,
                $personId
            );
        } else {
            $file = $fileService->saveFromContent(
                $content,
                "client_portal_logo_$personId",
                '',
                'person',
                $personId,
                null,
                $personId,
                $typeId
            );
        }

        $settingsData = $this->getSettings($personId);

        $settingsExist = $settingsData && isset($settingsData->settings);

        if ($settingsExist) {
            $data = $settingsData->settings;
            $json = json_decode($data, true);
        } else {
            $json = [];
        }

        $json['logo'] = [
            'id'  => $file->getId(),
            'crc' => $file->getCrc(),
        ];

        /** @var Builder $settingTable */
        $settingTable = DB::table('client_portal_company_settings');

        if ($settingsExist) {
            $settingTable
                ->where('company_person_id', '=', $personId)
                ->update([
                    'settings' => json_encode($json),
                ]);
        } else {
            $settingTable->insert([
                'company_person_id' => $personId,
                'settings'          => json_encode($json),
            ]);
        }

        if ($file) {
            $logoId = $file->getId();
            $links = $fileService->getFileLinks([$logoId], ['logo.png']);

            return [
                'link' => $links['links'][$logoId],
            ];
        }

        return null;
    }

    /**
     * Get company name from database
     *
     * @param $companyPersonId
     *
     * @return mixed
     */
    private function getCompanyName($companyPersonId)
    {
        /** @var Person|EloquentBuilder $personModel */
        $personModel = $this->app[Person::class];

        $person = $personModel
            ->where('kind', 'company')
            ->where('person_id', $companyPersonId)
            ->firstOrFail();

        return $person->custom_1;
    }

    /**
     * Get company from database
     *
     * @param $companyPersonId
     *
     * @return mixed
     */
    private function getCompany($companyPersonId)
    {
        /** @var Person|EloquentBuilder $personModel */
        $personModel = $this->app[Person::class];

        $person = $personModel
            ->where('kind', 'company')
            ->where('person_id', $companyPersonId)
            ->firstOrFail();

        return $person;
    }

    /**
     * Post store logo image request
     *
     * @param $clientPortalUrl
     * @param $post
     *
     * @return mixed
     *
     * @throws RuntimeException
     */
    private function postStoreLogoImageRequest($clientPortalUrl, $post)
    {
        try {
            $response = $this->client->post($clientPortalUrl . 'remote/upload_logo', [
                'form_params' => [
                    $post,
                ],
            ]);
        } catch (Exception $e) {
            throw new RuntimeException('Error while sending logo to client portal: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Resize image to 50px height and save as png
     *
     * @param SplFileInfo $logo
     * @param             $personId
     * @param             $company
     *
     * @return string
     *
     * @throws RuntimeException
     */
    private function resizeImage($logo, $personId, $company)
    {
        $imgPath = storage_path('/app/') . 'tmp_client_portal_logo_' . $personId . '_' . md5($company) . '.png';

        try {
            // Create a new SimpleImage object
            $image = new SimpleImage($logo->getPathname());

            // resize to 60px height
            $image
                ->fit_to_height(50)
                ->save($imgPath, 80, 'png');
        } catch (Exception $e) {
            throw new RuntimeException('Error while resizing logo: : ' . $e->getMessage());
        }

        return $imgPath;
    }
}
