<?php

namespace App\Http\Controllers\Quote;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Webkul\Admin\Http\Controllers\Quote\QuoteController as BaseQuoteController;

class QuoteController extends BaseQuoteController
{
    /**
     * Print and download the for the specified resource.
     *
     * Uses letter size paper instead of A4.
     */
    public function print($id): Response|StreamedResponse
    {
        $quote = $this->quoteRepository->findOrFail($id);

        $html = view('admin::quotes.pdf', compact('quote'))->render();
        $fileName = 'Quote_'.$quote->subject.'_'.$quote->created_at->format('d-m-Y');

        return $this->downloadLetterPDF($html, $fileName);
    }

    /**
     * Download PDF with letter size paper.
     */
    protected function downloadLetterPDF(string $html, ?string $fileName = null): Response|StreamedResponse
    {
        if (is_null($fileName)) {
            $fileName = Str::random(32);
        }

        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        if (in_array($direction = app()->getLocale(), ['ar', 'he'])) {
            $mPDF = new Mpdf([
                'margin_left'   => 0,
                'margin_right'  => 0,
                'margin_top'    => 0,
                'margin_bottom' => 0,
                'format'        => 'Letter',
            ]);

            $mPDF->SetDirectionality($direction);
            $mPDF->SetDisplayMode('fullpage');
            $mPDF->WriteHTML($this->adjustArabicAndPersianContent($html));

            return response()->streamDownload(fn () => print ($mPDF->Output('', 'S')), $fileName.'.pdf');
        }

        return PDF::loadHTML($this->adjustArabicAndPersianContent($html))
            ->setPaper('letter', 'portrait')
            ->set_option('defaultFont', 'Courier')
            ->download($fileName.'.pdf');
    }
}
