<?php

namespace App\Console\Commands;

use App\Models\Berita;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncBeritaSmkn extends Command
{
    protected $signature = 'berita:sync-smkn';

    protected $description = 'Sinkronisasi berita dari website SMKN 1 Bawang';

    public function handle()
    {
        $feedUrl = 'https://smkn1bawang.sch.id/feed/';

        try {
            $response = Http::timeout(20)
                ->accept('application/rss+xml, application/xml, text/xml')
                ->get($feedUrl);

            if ($response->failed()) {
                $this->error('Gagal mengambil feed.');
                return self::FAILURE;
            }

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA);

            if (!$xml || !isset($xml->channel->item)) {
                $this->error('Format feed tidak valid.');
                return self::FAILURE;
            }

            $jumlah = 0;

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

                $jumlah++;
            }

            $this->info("Sinkronisasi selesai. Total data diproses: {$jumlah}");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Terjadi kesalahan: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
