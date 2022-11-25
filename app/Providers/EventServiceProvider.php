<?php

namespace App\Providers;

use App\Core\Exceptions\QueryBindingException;
use App\Core\Oauth2\AccessToken;
use App\Core\Oauth2\AccessTokenRecordService;
use App\Modules\Bill\Models\Bill;
use App\Modules\Bill\Models\BillEntry;
use App\Modules\Bill\Services\BillService;
use App\Modules\CalendarEvent\Models\CalendarEvent;
use App\Modules\CalendarEvent\Services\CalendarEventRecordService;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceEntry;
use App\Modules\Invoice\Services\InvoiceService;
use App\Modules\Mainmenu\Models\Mainmenu;
use App\Modules\Mainmenu\Models\MainmenuItem;
use App\Modules\Mainmenu\Repositories\MainmenuRoleRepository;
use App\Modules\TimeSheet\Models\TimeSheet;
use App\Modules\TimeSheet\Services\TimeSheetService;
use App\Modules\WorkOrder\Models\LinkPersonWo;
use App\Modules\WorkOrder\Models\WorkOrder;
use App\Modules\WorkOrder\Services\LinkPersonWoRecordService;
use App\Modules\WorkOrder\Services\WorkOrderRecordService;
use Exception;
use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\App;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen
        = [
            'event.name' => [
                'EventListener',
            ],
        ];


    /**
     * Register any other events for your application.
     *
     * @return void
     */
    public function boot()
    {
        TimeSheet::created(function ($timeSheet) {
            /** @var TimeSheetService $tsService */
            $tsService = $this->app->make(TimeSheetService::class);
            $tsService->updateDurationTimes($timeSheet);
        });

        TimeSheet::updated(function ($timeSheet) {
            /** @var TimeSheetService $tsService */
            $tsService = $this->app->make(TimeSheetService::class);
            $tsService->onTimeSheetChange($timeSheet);
        });

        // update work order supplier person id after bill is created/updated
        Bill::saved(function ($bill) {
            $billService = $this->app->make(BillService::class);
            $billService->updateWoSupplierPersonId($bill);
        });

        // update bills when bill entries are created/updated/deleted
        BillEntry::saved(function ($billEntry) {
            $billService = $this->app->make(BillService::class);
            $billService->updateAmount($billEntry);
        });
        BillEntry::deleted(function ($billEntry) {
            $billService = $this->app->make(BillService::class);
            $billService->updateAmount($billEntry);
        });

        // update invoice entries (and their relations) after invoice was deleted
        Invoice::deleted(function ($invoice) {
            $invoiceService = $this->app->make(InvoiceService::class);
            $invoiceService->detachEntries($invoice);
        });

        InvoiceEntry::saved(function ($invoice) {
            // @todo (or not)
        });

        InvoiceEntry::deleted(function ($invoice) {
            $invoiceService = $this->app->make(InvoiceService::class);
            $invoiceService->detachLinkedRecords($invoice);
            // @todo more (or not) same as in saved
        });

        // create and update link person wo actions
        LinkPersonWo::created(function ($lpWo) {
            $lpWoService = $this->app->make(LinkPersonWoRecordService::class);
            $lpWoService->inserted($lpWo);
        });

        LinkPersonWo::updated(function ($lpWo) {
            $lpWoService = $this->app->make(LinkPersonWoRecordService::class);
            $lpWoService->updated($lpWo);
        });

        WorkOrder::created(function ($wo) {
            $woService = $this->app->make(WorkOrderRecordService::class);
            $woService->created($wo);
        });

        // clear main menu role cache when menu/menu items are created/updated/deleted
        Mainmenu::saved(function () {
            $mmRepo = $this->app->make(MainMenuRoleRepository::class);
            $mmRepo->clearCache();
        });

        Mainmenu::deleted(function () {
            $mmRepo = $this->app->make(MainMenuRoleRepository::class);
            $mmRepo->clearCache();
        });
        MainmenuItem::saved(function () {
            $mmRepo = $this->app->make(MainMenuRoleRepository::class);
            $mmRepo->clearCache();
        });
        MainmenuItem::deleted(function () {
            $mmRepo = $this->app->make(MainMenuRoleRepository::class);
            $mmRepo->clearCache();
        });

        // create calendar event action
        CalendarEvent::created(function ($calEvent) {
            $calService = $this->app->make(CalendarEventRecordService::class);
            $calService->inserted($calEvent);
        });

        // access token delete action
        AccessToken::deleted(function ($token) {
            $accTokService = $this->app->make(AccessTokenRecordService::class);
            $accTokService->deleted($token);
        });

        /* Monitoring SQL queries for DEV purposes
           It should probably not be used for production if not necessary
        */

        $sqlConf = $this->app->config->get('database.log');

        if ($sqlConf['log_queries'] === true
            || $sqlConf['log_slow_queries'] === true
        ) {
            Event::listen(
                \Illuminate\Database\Events\QueryExecuted::class,
                function ($event) use ($sqlConf) {
                    $sql = $event->sql;
                    $bindings = $event->bindings;
                    $time = $event->time;

                    $sql = str_replace(
                        ['%', '?', "\n"],
                        ['%%', "'%s'", ' '],
                        $sql
                    );
                    try {
                        $fullSql = vsprintf($sql, $bindings);
                    } catch (Exception $e) {
                        // there are some objects that couldn't be converted to string
                        /** @var  QueryBindingException $exp */
                        $exp = App::make(QueryBindingException::class);
                        $exp->setData(['query' => $sql]);
                        $exp->log();
                        $fullSql = 'NOT BINDED SQL: ' . $sql;
                    }
                    $logData = '/*  ' . date('Y-m-d H:i:s') . ' [' .
                        ($time / 1000.0) . 's]' . "  */\n" . $fullSql . ';' .
                        "\n/*==================================================*/"
                        . "\n";

                    if ($sqlConf['log_queries'] === true) {
                        $fileName = $sqlConf['directory'] . DIRECTORY_SEPARATOR
                            . date('Y-m-d') . '-log.sql';
                        file_put_contents($fileName, $logData, FILE_APPEND);
                        exec('sudo chmod 0777 ' . $fileName);
                    }
                    if ($sqlConf['log_slow_queries'] === true
                        && $time > $sqlConf['slow_queries_time']
                    ) {
                        $fileName = $sqlConf['directory'] . DIRECTORY_SEPARATOR
                            . date('Y-m-d') . '-slow-log.sql';
                        file_put_contents($fileName, $logData, FILE_APPEND);
                        exec('sudo chmod 0777 ' . $fileName);
                    }
                }
            );
        }
    }
}
