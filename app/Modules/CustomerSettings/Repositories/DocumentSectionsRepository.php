<?php

namespace App\Modules\CustomerSettings\Repositories;

use App\Core\AbstractRepository;
use App\Modules\CustomerSettings\Models\DocumentSections;
use Illuminate\Container\Container;

/**
 * DocumentSections repository class
 */
class DocumentSectionsRepository extends AbstractRepository
{
    /**
     * Repository constructor
     *
     * @param Container        $app
     * @param DocumentSections $documentSections
     */
    public function __construct(
        Container $app,
        DocumentSections $documentSections
    ) {
        parent::__construct($app, $documentSections);
    }

    /**
     * Get all document sections for given customer setting
     *
     * @param int    $customerSettingId
     * @param string $document document type
     * @param string $section  document section
     *
     * @return DocumentSections
     */
    public function getForCustomerSetting($customerSettingId, $document = null, $section = null)
    {
        // set base model query
        $query = $this->model->where('customer_setting_id', $customerSettingId);

        // add document query
        if ($document) {
            $query->where('document', $document);
        }

        // add document query
        if ($section) {
            $query->where('section', $section);
        }

        // get results
        return $query->orderBy('ordering')->orderBy('id')->get();
    }

    /**
     * Get collection of document sections
     *
     * @param array $ids array of section's id's to select
     *
     * @return DocumentSections
     */
    public function getCollection(array $ids)
    {
        // set base model query
        $query = $this->model->whereIn('id', $ids);

        // get results
        return $query->orderBy('ordering')->orderBy('id')->get();
    }
}
