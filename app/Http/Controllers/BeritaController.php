<?php

namespace App\Http\Controllers;

use App\Models\Berita;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BeritaController extends Controller
{
    /**
     * URL feed utama WordPress
     */
    private string $feedUrl = 'https://smkn1bawang.sch.id/feed/';

    /**
     * Tampilkan daftar berita dari database
     */
    public function index(Request $request)
    {
        $keyword = $request->keyword;

        $query = Berita::query();

        if ($keyword) {
            $query->where('judul', 'like', '%' . $keyword . '%')
                  ->orWhere('deskripsi', 'like', '%' . $keyword . '%')
                  ->orWhere('kategori', 'like', '%' . $keyword . '%');
        }

                $beritas = $query->orderByDesc('tanggal_publish')
                          ->paginate(10)
                          ->withQueryString();

        return view('berita.index', compact('beritas', 'keyword'));
    }

    /**
     * Sinkronisasi berita dari feed
     */
    public function sync()
    {
        try {
            $response = Http::timeout(20)
                ->accept('application/rss+xml, application/xml, text/xml')
                ->get($this->feedUrl);

            if ($response->failed()) {
                return redirect()
                    ->route('berita.index')
                    ->with('error', 'Gagal mengambil data feed dari website SMKN 1 Bawang.');

                                }

            $xmlString = $response->body();

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlString, 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml) {
                return redirect()
                    ->route('berita.index')
                    ->with('error', 'Format XML feed tidak valid atau tidak dapat dibaca.');
            }

            $jumlahTersimpan = 0;

            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $title = isset($item->title) ? trim((string) $item->title) : null;
                    $link = isset($item->link) ? trim((string) $item->link) : null;

                    $description = isset($item->description) ? trim(strip_tags((string) $item->description)) : null;
                    $author = isset($item->children('dc', true)->creator)
                        ? trim((string) $item->children('dc', true)->creator)
                        : null;

                    $kategori = null;
                    if (isset($item->category)) {
                        $kategoriData = [];
                        foreach ($item->category as $cat) {
                            $kategoriData[] = trim((string) $cat);
                        }
                        $kategori = implode(', ', array_filter($kategoriData));
                    }

                    $tanggalPublish = null;
                    if (isset($item->pubDate) && !empty((string) $item->pubDate)) {
                        $timestamp = strtotime((string) $item->pubDate);
                        if ($timestamp !== false) {
                            $tanggalPublish = date('Y-m-d H:i:s', $timestamp);
                        }
                    }

                                        if (!$title || !$link) {
                        continue;
                    }

                    Berita::updateOrCreate(
                        ['link' => $link],
                        [
                            'judul' => $title,
                            'deskripsi' => $description,
                            'author' => $author,
                            'kategori' => $kategori,
                            'tanggal_publish' => $tanggalPublish,
                        ]
                    );

                    $jumlahTersimpan++;
                }
            }

            return redirect()
                ->route('berita.index')
                ->with('success', 'Sinkronisasi berhasil. Total data diproses: ' . $jumlahTersimpan . ' berita.');

                        } catch (\Throwable $e) {
            return redirect()
                ->route('berita.index')
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    /**
     * Hapus semua berita
     */
    public function truncate()
    {
        Berita::truncate();

        return redirect()
            ->route('berita.index')
            ->with('success', 'Semua data berita berhasil dihapus.');
    }
}