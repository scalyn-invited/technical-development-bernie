const orders = [
  { id: 1, customer: 'Alice', items: [{name: 'Widget', qty: 2}, {name: 'Gadget', qty: 1}], total: 150, status: 'completed', placedAt: '2026-08-20' },
  { id: 2, customer: 'Bob', items: [{name: 'Doohickey', qty: 3}], total: 200, status: 'completed', placedAt: '2026-08-21' },
  { id: 3, customer: 'Alice', items: [{name: 'Thingamajig', qty: 1}], total: 75, status: 'pending', placedAt: '2026-08-22' },
  { id: 4, customer: 'Charlie', items: [{name: 'Widget', qty: 5}], total: 500, status: 'completed', placedAt: '2026-08-19' },
  { id: 5, customer: 'Diana', items: [{name: 'Gadget', qty: 2}], total: 300, status: 'cancelled', placedAt: '2026-08-18' },
  { id: 6, customer: 'Eve', items: [{name: 'Widget', qty: 1}], total: 75, status: 'completed', placedAt: '2026-08-21' },
  { id: 7, customer: 'Bob', items: [{name: 'Doohickey', qty: 1}], total: 100, status: 'pending', placedAt: '2026-08-20' },
  { id: 8, customer: 'Alice', items: [{name: 'Widget', qty: 3}], total: 225, status: 'completed', placedAt: '2026-08-19' },
  { id: 9, customer: 'Frank', items: [{name: 'Thingamajig', qty: 2}], total: 150, status: 'completed', placedAt: '2026-08-21' },
  { id: 10, customer: 'Grace', items: [{name: 'Gadget', qty: 1}, {name: 'Widget', qty: 1}], total: 125, status: 'completed', placedAt: '2026-08-22' },
  { id: 11, customer: 'Henry', items: [{name: 'Doohickey', qty: 4}], total: 400, status: 'pending', placedAt: '2026-08-20' },
  { id: 12, customer: 'Alice', items: [{name: 'Gadget', qty: 3}], total: 300, status: 'completed', placedAt: '2026-08-21' },
  { id: 13, customer: 'Ivy', items: [{name: 'Widget', qty: 2}], total: 200, status: 'completed', placedAt: '2026-08-18' },
  { id: 14, customer: 'Jack', items: [{name: 'Thingamajig', qty: 1}], total: 75, status: 'cancelled', placedAt: '2026-08-22' },
  { id: 15, customer: 'Charlie', items: [{name: 'Gadget', qty: 1}], total: 150, status: 'completed', placedAt: '2026-08-21' },
];

// totalRevenueCompleted() — Return the sum of total for all orders with status === 'completed'.
function getCompletedOrders() {
    return orders.filter(o=> o.status === 'completed');
}

//console.log(getCompletedOrders());


// ordersByStatus() — Return an object where keys are status values ('completed', 'pending', 'cancelled') and values are arrays of orders.
function ordersByStatus() {

    return orders.reduce((groups, order) => {
        const status = order.status;
        if (!groups[status]) {
            groups[status] = [];
        }

        groups[status].push(order);
        return groups;
    });
}

//console.log(ordersByStatus());


//topThreeCustomersBySpend() — Return an array of three customer names (strings), ordered by total spend descending.

function topThreeCustomersBySpend() {
    return orders.sort((a, b) => b.total - a.total)
        .slice(0, 3)
        .map(order => order.customer);
}

//console.log(topThreeCustomersBySpend());


//flatItemList() — Return a flat array of all item names (strings) from every order, with no duplicates.

function flatItemList() {
    return orders.map(order => order.items.map(item => item.name)).flat().filter((value, index, self) => self.indexOf(value) === index);    
}

console.log(flatItemList());
