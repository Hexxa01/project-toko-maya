<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\XlsxWriter;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    private function sales(Request $r)
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from']);

        return Order::where('payment_status', 'paid')->when($r->from, fn ($q) => $q->whereDate('paid_at', '>=', $r->from))->when($r->to, fn ($q) => $q->whereDate('paid_at', '<=', $r->to))->orderBy('paid_at')->orderBy('id');
    }

    public function xlsx(Request $r, XlsxWriter $writer)
    {
        $query = $this->sales($r);
        $rows = (function () use ($query) {
            yield ['Kode Invoice', 'Tanggal Lunas', 'Pelanggan', 'Kanal', 'Status', 'Subtotal', 'Ongkir', 'Total'];
            foreach ($query->cursor() as $o) {
                yield [$o->code, $o->paid_at->format('Y-m-d H:i:s'), $o->recipient, $o->channel, $o->status, (int) $o->subtotal, (int) $o->shipping_cost, (int) $o->total];
            }
        })();
        $file = $writer->create($rows);

        return response()->download($file, 'laporan-toko-maya-'.now()->format('Ymd').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    private function pdf(string $view, array $data, string $filename, string $orientation = 'portrait')
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view, $data)->render());
        $pdf->setPaper('A4', $orientation);
        $pdf->render();

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"']);
    }

    public function report(Request $r)
    {
        $query = $this->sales($r);

        return $this->pdf('documents.report', ['orders' => $query->get(), 'from' => $r->from, 'to' => $r->to], 'laporan-toko-maya-'.now()->format('Ymd').'.pdf', 'landscape');
    }

    public function receipt(Request $r, Order $order)
    {
        abort_unless($order->user_id === $r->user()->id || in_array($r->user()->role, ['kasir', 'pemilik']), 403);
        $r->validate(['width' => 'nullable|in:58,80']);

        return view('documents.receipt', ['order' => $order->load(['items', 'cashier']), 'width' => (int) $r->input('width', 80)]);
    }

    public function shipping(Request $r, Order $order)
    {
        abort_unless($order->payment_status === 'paid' && $order->status !== 'cancelled', 422);
        $r->validate(['type' => 'nullable|in:label,delivery', 'format' => 'nullable|in:html,pdf']);
        $data = ['order' => $order->load('items'), 'type' => $r->input('type', 'delivery')];

        return $r->input('format') === 'pdf' ? $this->pdf('documents.shipping',$data,'surat-jalan-'.$order->code.'.pdf') : view('documents.shipping',$data);
    }
}
