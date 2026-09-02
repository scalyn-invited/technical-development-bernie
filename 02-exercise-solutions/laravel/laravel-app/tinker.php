
use App\Models\Customer;
use App\Models\Order;

DB::enableQueryLog();

DB::flushQueryLog();
DB::enableQueryLog();

$orders = Order::all();
$customers = [];
foreach ($orders as $order) {
    $customers[] = $order->customer->name;
}

$log = DB::getQueryLog();
echo "Total queries: " . count($log);

count(DB::getQueryLog());