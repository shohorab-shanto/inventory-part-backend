<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequisitionCollection;
use App\Http\Resources\RequisitionResource;
use App\Models\Part;
use Illuminate\Http\Request;
use App\Models\Requisition;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;


class ClientRequisitionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

     $company = auth()->user()->details?->company;
        if (!$company)
            return message('Unathorized access', 403);

        $requisitions = $company->requisitions()
            ->with(
                'quotation',
                'company:id,name,logo',
                'machines:id,machine_model_id',
                'machines.model:id,name'
            )->latest();

        //Search the quatation
        if ($request->q)
            $requisitions = $requisitions->where(function ($requisitions) use ($request) {
                //Search the data by company name and id
                $requisitions = $requisitions->where('rq_number', 'LIKE', '%' . $request->q . '%');
            });

            if ($request->type)
        $requisitions = $requisitions->where(function ($requisitions) use ($request) {
            //Search the data by company name and id
            $requisitions = $requisitions->where('type',$request->type);
        });

        if ($request->status)
        $requisitions = $requisitions->where(function ($requisitions) use ($request) {
            //Search the data by company name and id
            $requisitions = $requisitions->where('status',$request->status);
        });

        //Check if request wants all data of the requisitions
        if ($request->rows == 'all')
            return RequisitionCollection::collection($requisitions->get());

        $requisitions = $requisitions->paginate($request->get('rows', 10));

        // return ['return',$requisitions];

        return RequisitionCollection::collection($requisitions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'part_items' => 'required|min:1',
            // 'expected_delivery' => 'required',
            'company_id' => 'required|exists:companies,id',
            'machine_id' => 'required|exists:company_machines,id',
            'engineer_id' => 'nullable|exists:users,id',
            'priority' => 'required|in:low,medium,high',
            'payment_mode' => 'required_if:type,purchase_request',
            'payment_term' => 'required_if:type,purchase_request',
            'type' => 'required|in:claim_report,purchase_request',
            // 'payment_partial_mode' => 'required_if:payment_term,partial',
            // 'partial_time' => 'required_if:payment_term,partial',
            'next_payment' => 'required_if:payment_term,partial',
        ]);

        // try {
            DB::beginTransaction();
            //Grab the data for the next procedure
            $data = $request->except('partItems');

            //Fill the requisition data
            $requisition = new Requisition();
            $requisition->fill($data);

            //taking part stock
            $parts = Part::with([
                'stocks' => fn ($q) => $q->where('unit_value', '>', 0)
            ])->find(collect($request->part_items)->pluck('id'));

            //Parse the part items
            $reqItems = collect($request->part_items);
            $items = $reqItems->map(function ($dt) use ($parts) {
                $stock = $parts->find($dt['id'])->stocks->last();

                return [
                    'part_id' => $dt['id'],
                    'name' => $dt['name'],
                    'quantity' => $dt['quantity'],
                    'unit_value' => $stock->selling_price ?? null,
                    'total_value' => $dt['quantity'] *  ($stock->selling_price ?? 0),
                    'remarks' => $dt['remarks'] ?? ''
                ];
            });

            // $stockOutItems = $items->filter(fn ($dt) => !$dt['unit_value'])->values();
            // if ($stockOutItems->count())
            //     return message('"' . $stockOutItems[0]['name'] . '" is out of stock', 400);

            //get the company
            $company = auth()->user()->details?->company;

            //Set requisition status based on the limit
            if ($items->sum('total_value') > $company->trade_limit){
                $requisition->status = 'pending';
            }else{
            $requisition->status = 'approved';
            }

            //Save the requisition
            $requisition->save();

            //Attach the machines
            $requisition->machines()->sync($data['machine_id']);

            //Create the part items of the requisition
            $requisition->partItems()->createMany($items);


            DB::commit();
            return message('Requisition created successfully', 200, $requisition);
        // } catch (\Throwable $th) {
        //     DB::rollback();
        //     return message(
        //         $th->getMessage(),
        //         400
        //     );
        // }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Requisition $clientRequisition)
    {
        $clientRequisition->load([
            'quotation',
            'company',
            'machines:id,machine_model_id',
            'machines.model:id,name',
            'engineer',
            'partItems.part.aliases',
            'partItems.part.stocks' => function ($q) {
                $q->where('unit_value', '>', 0);
            }
        ]);

        return RequisitionResource::make($clientRequisition);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Upload files and associate with the requisition
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Requisition $requisition
     * @return void
     */
    public function uploadFiles(Request $request, Requisition $requisition)
    {

        return $request;
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|mimes:png,jpg,pdf,xlsx,xls,csv,doc,docx,txt,zip'
        ]);
        foreach ($request->file('files') as $file)
            $requisition->addMedia($file)
                ->preservingOriginal()
                ->toMediaCollection('requisition-files');

        return message('Files uploaded successfully');
    }

    public function getFiles(Requisition $requisition)
    {
        $file = $requisition->getMedia('requisition-files')->toArray();

        return ['data' => $file];
    }

    public function deleteFiles(Request $request, Requisition $requisition, Media $media)
    {
       $requisition->deleteMedia($media);
       return message('Files deleted successfully');
    }
}
