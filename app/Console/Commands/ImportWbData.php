<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Sale;
use App\Models\Order;
use App\Models\Stock;
use App\Models\Income;

class ImportWbData extends Command
{
    protected $signature = 'wb:import-all';

    protected $description = 'Импортирует все данные из WB API в базу данных';

    protected $apiHost = 'http://109.73.206.144:6969';
    protected $apiKey = 'E6kUTYrYwZq2tN4QEtyzsbEBk3ie';

    public function handle()
    {
        $this->importSales();
        $this->importOrders();
        $this->importStocks();
        $this->importIncomes();
    }

    protected function importSales()
    {
        $this->info('Импортируем продажи...');
        $endpoint = '/api/sales';
        $this->fetchAndSave($endpoint, Sale::class, 'sales');
    }

    protected function importOrders()
    {
        $this->info('Импортируем заказы...');
        $endpoint = '/api/orders';
        $this->fetchAndSave($endpoint, Order::class, 'orders');
    }

    protected function importStocks()
    {
        $this->info('Импортируем склады...');
        $endpoint = '/api/stocks';
        $this->fetchAndSave($endpoint, Stock::class, 'stocks', [
            'dateFrom' => date('Y-m-d')
        ]);
    }

    protected function importIncomes()
    {
        $this->info('Импортируем доходы...');
        $endpoint = '/api/incomes';
        $this->fetchAndSave($endpoint, Income::class, 'incomes');
    }

    protected function fetchAndSave($endpoint, $model, $type, $extraParams = [])
    {
        $page = 1;
        $limit = 500;
        $hasMore = true;

        $dateFrom = $extraParams['dateFrom'] ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $extraParams['dateTo'] ?? date('Y-m-d');

        while ($hasMore) {
            $params = array_merge([
                'dateFrom' => $dateFrom,
                'dateTo'   => $dateTo,
                'page'     => $page,
                'limit'    => $limit,
                'key'      => $this->apiKey
            ], $extraParams);

            $url = $this->apiHost . $endpoint . '?' . http_build_query($params);

            $response = Http::get($url);
            if (!$response->ok()) {
                $this->error("Ошибка запроса: " . $response->status());
                break;
            }

            $data = $response->json();
            if (empty($data)) {
                $this->info('Нет данных для страницы ' . $page);
                break;
            }

            foreach ($data as $row) {
                if ($type === 'sales') {
                    $model::create([
                        'order_id'        => $row['order_id'] ?? null,
                        'item_id'         => $row['item_id'] ?? null,
                        'warehouse_name'  => $row['warehouse_name'] ?? null,
                        'quantity'        => $row['quantity'] ?? null,
                        'price'           => $row['price'] ?? null,
                        'total_amount'    => $row['total_amount'] ?? $row['total_price'] ?? null,
                        'date'            => $row['date'] ?? null,
                    ]);
                } elseif ($type === 'orders') {
                    $model::create([
                        'order_id'        => $row['order_id'] ?? $row['id'] ?? null,
                        'status'          => $row['status'] ?? null,
                        'client_name'     => $row['client_name'] ?? null,
                        'total_price'     => $row['total_price'] ?? null,
                        'date_created'    => $row['date_created'] ?? null,
                    ]);
                } elseif ($type === 'stocks') {
                    $model::create([
                        'supplier_article' => $row['supplier_article'] ?? null,
                        'tech_size'        => $row['tech_size'] ?? null,
                        'barcode'          => $row['barcode'] ?? null,
                        'quantity'         => $row['quantity'] ?? null,
                        'price'            => $row['price'] ?? null,
                        'date'             => $row['date'] ?? null,
                        'warehouse_name'   => $row['warehouse_name'] ?? null,
                        'nm_id'            => $row['nm_id'] ?? null,
                    ]);
                } elseif ($type === 'incomes') {
                    $model::create([
                        'income_id'        => $row['income_id'] ?? null,
                        'number'           => $row['number'] ?? null,
                        'date'             => $row['date'] ?? null,
                        'last_change_date' => $row['last_change_date'] ?? null,
                        'supplier_article' => $row['supplier_article'] ?? null,
                        'tech_size'        => $row['tech_size'] ?? null,
                        'barcode'          => $row['barcode'] ?? null,
                        'quantity'         => $row['quantity'] ?? null,
                        'total_price'      => $row['total_price'] ?? null,
                        'date_close'       => $row['date_close'] ?? null,
                        'warehouse_name'   => $row['warehouse_name'] ?? null,
                        'nm_id'            => $row['nm_id'] ?? null,
                    ]);
                }
            }

            $hasMore = count($data) === $limit;
            $page++;
        }
    }
}
