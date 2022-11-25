<?php

namespace App\Modules\WorkOrder\Repositories;

use App\Core\AbstractRepository;
use App\Modules\Asset\Models\LinkAssetWo;
use App\Modules\File\Services\FileService;
use Illuminate\Container\Container;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * LinkPersonWo repository class
 */
class LinkAssetWoRepository extends AbstractRepository
{
    /**
     * {@inheritdoc}
     */
    protected $searchable = ['work_order_id'];

    /**
     * Repository constructor
     *
     * @param Container   $app
     * @param LinkAssetWo $linkAssetWo
     */
    public function __construct(Container $app, LinkAssetWo $linkAssetWo)
    {
        parent::__construct($app, $linkAssetWo);
    }

    /**
     * Pagination - based on query url use either automatic paginator or
     * manual paginator
     *
     * @param int   $perPage
     * @param array $columns
     * @param array $order
     *
     * @return \Illuminate\Database\Eloquent\Collection|Paginator
     * @throws \App\Modules\File\Exceptions\NoDeviceForVolumeException
     */
    public function paginate(
        $perPage = 50,
        array $columns = ['*'],
        array $order = []
    ) {
        $model = $this->model;
        //if ($this->request->input('detailed', null) == 1) {
        //$model = $model->leftJoin('asset','asset.asset_id','=','link_asset_wo.asset_id');
        //}
        //$model->asset_info = 'dane asseta';
        //vaR_dump($this->request->input('work_order_id', null));
        //exit;
        //if (!empty($this->request->input('work_order_id', null)))
        //    $workOrderID = $this->request->input('work_order_id', null);
        //if (!empty($this->request->input('record_id', null)))
        //    $workOrderID = $this->request->input('record_id', null);
        //$workOrderID = 40;
        //vaR_dump($this->request->input('work_order_id'));
        //exit;
        // $model = $model->where('work_order_id', $workOrderID );
        //$model->files = array('dane asseta');
        $this->setWorkingModel($model);
        $data = parent::paginate($perPage, [], $order);
        foreach ($data as $key => $d) {
            $d->asset = [];
            $asset = DB::select('SELECT * FROM asset WHERE asset_id = ?', [$d->asset_id]);
            if ($asset && !empty($asset[0])) {
                $d->asset = $asset[0];
            }
            $d->files = [];
            $fileService = $this->app->make(FileService::class);
            $d->files =
                $fileService->getPhotos('link_asset_wo', $d->id, null, '250', '250', 1, 1);
            if (strlen(getTypeValueById($d->status_type_id)) > 0) {
                $d->status_name = getTypeValueById($d->status_type_id);
            } else {
                $d->status_name = 'none';
            }
        }
        $this->clearWorkingModel();

        return $data;
    }

    /**
     * Get work orders by asset id
     *
     * @param $assetId
     *
     * @return mixed
     */
    public function getWorkOrdersByAssetId($assetId)
    {
        return $this->model
            ->select([
                'work_order.work_order_id',
                'work_order.work_order_number',
                'work_order.received_date',
                'work_order.description',
                'link_asset_wo.link_asset_wo_id',
                'link_asset_wo.work_requested',
                'link_asset_wo.work_performed',
            ])
            ->join('work_order', 'link_asset_wo.work_order_id', '=', 'work_order.work_order_id')
            ->where('link_asset_wo.asset_id', $assetId)
            ->orderByDesc('work_order.received_date')
            ->get();
    }

    /**
     * Get link asset person wo by asset id and work order id
     * @param $assetId
     * @param $workOrderId
     *
     * @return mixed
     */
    public function getLinkAssetPersonWoByAssetIdAndWorkOrderId($assetId, $workOrderId)
    {
        return DB::table('link_asset_person_wo')
            ->select([
                'link_asset_person_wo.*',
                DB::raw('person_name(link_asset_person_wo.person_id) as person_name')
            ])
            ->where('asset_id', $assetId)
            ->where('work_order_id', $workOrderId)
            ->get();
    }
}
