<?php

namespace App\Modules\Invoice\Repositories;

use App\Core\AbstractRepository;
use Carbon\Carbon;
use Illuminate\Container\Container;
use App\Modules\Invoice\Models\InvoiceImportException;

/**
 * Invoice import exception repository class
 */
class InvoiceImportExceptionRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = [
        'invoice_number',
        'work_order_number',
        'customer',
        'error_message',
        'reported_at',
        'resolved_at',
    ];

    protected $sortable = [
        'invoice_number',
        'work_order_number',
        'customer',
        'error_message',
        'reported_at',
        'resolved_at',
    ];

    /**
     * Repository constructor
     *
     * @param Container $app
     * @param Invoice $invoice
     */
    public function __construct(
        Container $app,
        InvoiceImportException $model
    ) {
        parent::__construct($app, $model);
    }

    /**
     * Apply conditions to the main query
     * @param  Builder $model
     * @param  array  $conditions
     * @return Builder
     */
    protected function applyConditions($model, array $conditions)
    {
        foreach ($conditions as $cond) {
            $column = $cond['column'];
            $value = $cond['value'];

            if ($column == 'resolved_at') {
                if ($value == 'yes') {
                    $model = $model->whereNotNull('resolved_at');
                } else {
                    $model = $model->whereNull('resolved_at');
                }
            } else {
                $model = $model->where($column, 'like', "%{$value}%");
            }
        }

        return $model;
    }

    /**
     * Resolve import exception
     * @param  int $id
     * @return InvoiceImportException
     */
    public function resolve($id)
    {
        return $this->updateResolvedAt($id, (string) Carbon::now());
    }

    /**
     * Reopen import exception
     * @param  int $id
     * @return InvoiceImportException
     */
    public function reopen($id)
    {
        return $this->updateResolvedAt($id, null);
    }

    /**
     * Update resolved_at column on import exception by ID
     * @param  int $id
     * @param  mixed $value
     * @return InvoiceImportException
     */
    private function updateResolvedAt($id, $value)
    {
        $importException = $this->find($id);

        $importException->resolved_at = $value;
        $importException->save();

        return $importException;
    }
}
