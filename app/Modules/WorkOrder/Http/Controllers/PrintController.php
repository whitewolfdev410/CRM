<?php

namespace App\Modules\WorkOrder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WorkOrder\Http\Requests\PrintPdf\GeneratePdfRequest;
use App\Modules\WorkOrder\Http\Requests\PrintPdf\SendEmailRequest;
use App\Modules\WorkOrder\Http\Requests\PrintPdf\SendFaxRequest;
use App\Modules\WorkOrder\Repositories\LinkPersonWoRepository;
use App\Modules\WorkOrder\Services\LinkPersonWoPrintService;
use Illuminate\Config\Repository as Config;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Class PrintController
 *
 * @package App\Modules\WorkOrder\Http\Controllers
 */
class PrintController extends Controller
{
    /**
     * LinkPersonWo repository
     *
     * @var LinkPersonWoRepository
     */
    private $linkPersonWoRepository;

    /**
     * @var LinkPersonWoPrintService
     */
    private $printService;

    /**
     * Set repository and apply auth filter
     *
     * @param LinkPersonWoRepository $linkPersonWoRepository
     * @param LinkPersonWoPrintService $printService
     */
    public function __construct(
        LinkPersonWoRepository $linkPersonWoRepository,
        LinkPersonWoPrintService $printService
    ) {
        // @todo MANTIS-3842
        $this->middleware('auth', ['except' => 'download']);
        $this->linkPersonWoRepository = $linkPersonWoRepository;
        $this->printService = $printService;
    }

    /**
     * Show data to choose files and actions to generate PDF file
     *
     * @param int $id
     *
     * @return Response
     */
    public function choose($id)
    {
        $this->checkPermissions(['link-person-wo.print-choose']);
        $id = (int)$id;

        $data = $this->printService->choose((int)$id);

        return response()->json($data);
    }

    /**
     * Generate new Work order PDF file
     *
     * @param GeneratePdfRequest $request
     * @param int $id
     *
     * @return Response
     */
    public function generatePdf(GeneratePdfRequest $request, $id)
    {
        $this->checkPermissions(['link-person-wo.print-pdf-generate']);
        $id = (int)$id;

        $data = $this->printService->generatePdf((int)$id, $request);

        return response()->json($data, 201);
    }

    /**
     * Show data to send e-mail
     *
     * @param int $id
     *
     * @return Response
     */
    public function emailInfo($id)
    {
        $this->checkPermissions(['link-person-wo.print-email-info']);

        $data = $this->printService->showEmailInfo((int)$id);

        return response()->json($data);
    }

    /**
     * Send e-mail with PDF attachment and issue work order
     *
     * @param SendEmailRequest $request
     * @param  int $id
     *
     * @return Response
     */
    public function sendEmail(SendEmailRequest $request, $id)
    {
        $this->checkPermissions(['link-person-wo.print-email-send']);

        $data = $this->printService->sendEmail((int)$id, $request);

        return response()->json(['item' => $data]);
    }

    /**
     * Show data to send fax
     *
     * @param int $id
     *
     * @return Response
     */
    public function faxInfo($id)
    {
        $this->checkPermissions(['link-person-wo.print-fax-info']);

        $data = $this->printService->showFaxInfo((int)$id);

        return response()->json($data);
    }

    /**
     * Upload PDF attachment to Fax server and issue work order
     *
     * @param SendFaxRequest $request
     * @param int $id
     *
     * @return Response
     */
    public function sendFax(SendFaxRequest $request, $id)
    {
        $this->checkPermissions(['link-person-wo.print-fax-send']);

        $data = $this->printService->sendFax((int)$id, $request);

        return response()->json($data);
    }

    /**
     * Download PDF attachment
     *
     * @param Request $request
     * @param int $id
     *
     * @return Response
     */
    public function download(Request $request, $id)
    {
        // @todo MANTIS-3842
        // $this->checkPermissions(['link-person-wo.print-download']);

        return $this->printService->download((int)$id, $request);
    }
}
