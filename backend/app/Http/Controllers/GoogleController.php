<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheetsService;
use Illuminate\Support\Facades\Log;

class GoogleController extends Controller
{
    private $client;

    public function __construct()
    {
        $this->client = new GoogleClient();
        // $this->client->setAuthConfig(public_path('googlesheet.json'));
        $this->client->setAuthConfig(base_path('secure/magalisdemonew.json'));
        $this->client->addScope('https://www.googleapis.com/auth/spreadsheets');
        $this->client->setAccessType('offline');
    }

    public function getData()
    {
        $spreadsheetId = env('SPREAD_SHEET_ID');
        $range = 'Bosta';

        try {
            $service = new GoogleSheetsService($this->client);
            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            return response()->json($response->getValues());
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch data from Google Sheets', 'details' => $e->getMessage()], 500);
        }
    }

    public function addData(Request $request, $sheet)
    {
        $spreadsheetId =  env('SPREAD_SHEET_ID','1Aan2wHC7dNhb6SrS--QyWOJ9FlEhlZWwMsGGGPYWqtU');

        $sheetColumnMap = [
            'Lifters' => [
                'column' => 'B',
                'key' => 'Order Number',
                'columnsMap' => [
                    'Customer Name' => 0,
                    'Order Number' => 1,
                    'Customer Number' => 2,
                    'Customer Number 2' => 3,
                    'SKU' => 4,
                    'Item Description' => 5,
                    'Qty' => 6,
                    'Full Address' => 7,
                    'Governorate' => 8,
                    'City' => 9,
                    'Payment Method' => 10,
                    'COD' => 11,
                    'Delivery Notes' => 12,
                    'Creation Date' => 13
                ]
            ],
            'Raya' => [
                'column' => 'A',
                'key' => 'Order Number',
                'columnsMap' => [
                    'Order Number' => 0,
                    'COD' => 1,
                    'Comment' => 2,
                    'Consumer Name' => 3,
                    'Consumer Mobile' => 4,
                    'Consumer Mobile2' => 5,
                    'Consumer Districts' => 6,
                    'consumer Address' => 7,
                    'Shipper Name' => 8,
                    'Shipper Mobile' => 9,
                    'Shipper Districts' => 10,
                    'Shipper Address' => 11,
                    'Pieces' => 12,
                    'SKUs' => 13,
                    'Description' => 14,
                    'Category Size' => 15,
                    'Weight' => 16,
                ]
            ],
            'Mylerz' => [
                'column' => 'A',
                'key' => 'Package_Serial',
                'columnsMap' => [
                    'Package_Serial' => 0,
                    'Description' => 1,
                    'Service' => 2,
                    'Service_Type' => 3,
                    'Service_Category' => 4,
                    'Payment_Type' => 5,
                    'COD_Value' => 6,
                    'Quantity' => 7,
                    'Weight' => 8,
                    'Dimensions' => 9,
                    'Item_Category' => 10,
                    'Item_Special_Notes' => 11,
                    'Customer_Code' => 12,
                    'Customer_Name' => 13,
                    'Mobile_No' => 14,
                    'Building_No' => 15,
                    'Address' => 16,
                    'Floor_No' => 17,
                    'Apartment_No' => 18,
                    'City' => 19,
                    'Neighborhood' => 20,
                    'District' => 21,
                    'Address_Category' => 22,
                    'Package_Ref. Number' => 23,
                    'Piece_Ref. Number' => 24,
                    'Fulfillment' => 25,
                ]
            ],
            'Bosta' => [
                'column' => 'Q',
                'key' => 'Order Reference',
                'columnsMap' => [
                    'Full Name' => 0,
                    'Phone' => 1,
                    'Second Phone' => 2,
                    'City' => 3,
                    'Area' => 4,
                    'Street Name' => 5,
                    'Building' => 6,
                    'Floor' => 7,
                    'Apartment' => 8,
                    'Work address' => 9,
                    'Address' => 10,
                    'Delivery Notes' => 11,
                    'Type' => 12,
                    'Cash Amount' => 13,
                    'Items' => 14,
                    'Package Description' => 15,
                    'Order Reference' => 16,
                    'Allow Opening Package' => 17,
                ]
            ],
        ];

        if (!array_key_exists($sheet, $sheetColumnMap)) {
            return response()->json(['error' => 'Invalid sheet name'], 400);
        }

        try {
            $service = new GoogleSheetsService($this->client);
            $sheetConfig = $sheetColumnMap[$sheet];
            Log::alert("sheetConfig",[$sheetConfig]);
            Log::alert("sheet",[$sheet]);

            $range = $sheet . '!' . $sheetConfig['column'] . '2:' . $sheetConfig['column'];

            $existingData = $service->spreadsheets_values->get($spreadsheetId, $range)->getValues();

            $existingKeys = array_column($existingData, 0);
            Log::alert("existingKeys",[$existingKeys]);

            $newData = [];

            $data = json_decode($request->data, true);

            foreach ($data as $item) {
                $rowData = array_fill(0, count($sheetConfig['columnsMap']), '');
                $uniqueKey = $item[$sheetConfig['key']] ?? null;

                if (in_array($uniqueKey, $existingKeys)) {
                    continue;
                }

                foreach ($item as $key => $value) {
                    if (array_key_exists($key, $sheetConfig['columnsMap'])) {
                        $columnIndex = $sheetConfig['columnsMap'][$key];
                        $rowData[$columnIndex] = $value;
                    }
                }

                $newData[] = $rowData;
            }

            if (!empty($newData)) {
                $body = new \Google\Service\Sheets\ValueRange([
                    'values' => $newData
                ]);
                $params = [
                    'valueInputOption' => 'RAW'
                ];

                $service->spreadsheets_values->append($spreadsheetId, $sheet . '!A2' , $body, $params);

                return response()->json(['message' => 'Data added successfully']);
            }

            return response()->json(['message' => 'No new data to add'], 400);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to add data to Google Sheets', 'details' => $e->getMessage()], 500);
        }
    }






}
