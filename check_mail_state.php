<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Jobs: ".Illuminate\Support\Facades\DB::table('jobs')->count().PHP_EOL;
echo "Failed jobs: ".Illuminate\Support\Facades\DB::table('failed_jobs')->count().PHP_EOL;
$lastOrder = App\Models\Order::latest()->first();
if ($lastOrder) {
    echo "Last order ref: {$lastOrder->reference}".PHP_EOL;
    echo "Last order email: ".($lastOrder->customer_email ?? 'NULL').PHP_EOL;
    echo "Last order status: {$lastOrder->status}".PHP_EOL;
}
