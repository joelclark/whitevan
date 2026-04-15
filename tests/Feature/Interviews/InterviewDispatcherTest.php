<?php

use App\Enums\Trade;
use App\Interviews\InterviewDispatcher;
use App\Models\Account;
use App\Models\Customer;
use App\Models\Estimate;
use App\Trades\Flooring\Interview\FlooringInterview;

test('dispatcher returns FlooringInterview for flooring trade', function () {
    $account = Account::factory()->create();
    $customer = Customer::factory()->create(['account_id' => $account->id]);
    $estimate = Estimate::factory()->forCustomer($customer)->create(['trade' => Trade::Flooring]);

    $interview = InterviewDispatcher::for($estimate);

    expect($interview)->toBeInstanceOf(FlooringInterview::class);
});
