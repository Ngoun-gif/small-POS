<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use KHQR\BakongKHQR;
use KHQR\Helpers\KHQRData;
use KHQR\Models\IndividualInfo;

class BakongPaymentController extends Controller{

    public function generateQr(Request $request)
    {
        $total_price = $this->totalPrice();
        $total_price = $total_price - 1.5;
        $individualInfo = new IndividualInfo(
            bakongAccountID: 'khim_hengnguon@bkrt',
            merchantName: 'HENG NGUON KHIM',
            merchantCity: 'PHNOM PENH',
            currency: KHQRData::CURRENCY_USD,
            amount: $total_price
        );
        $qr = BakongKHQR::generateIndividual($individualInfo);
        return response()->json([
            'qr' => $qr,
            'amount' => $total_price,
            'currency' => 'USD',
            'merchantName' => 'HENG NGUON KHIM',
        ]);
    }
}
