<?php

namespace App\Commands;

use DOMDocument;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use Zttp\Zttp;

class GrundbuchCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'grundbuch';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display an inspiring quote';
    private CookieJar $cookie_jar;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

        $cookie_value = $this->ask('Please provide the session id value. Please refer to github.com/herpaderpaldent/dnip.');

        $this->cookie_jar = CookieJar::fromArray(['ASPSESSIONIDSABCQBQA' => $cookie_value], 'egov.ur.ch');

        $municipality_id = $this->ask('enter the municipality id (https://www.bfs.admin.ch/bfs/de/home/grundlagen/agvch/historisiertes-gemeindeverzeichnis.assetdetail.17884689.html)', 1207);
        $parcel_id_min = $this->ask('please enter the lower band parcel_id to analyze');
        $parcel_id_max = $this->ask('please enter the upper band parcel_id to analyze');;

        $file_name = $this->ask('how should the output csv be named?', 'output.csv');

        $file = fopen("$file_name", 'a');
        fputcsv($file, ['parcel_id', 'municipality_id', 'owner']);

        $start = Carbon::now();

        $range = range($parcel_id_min, $parcel_id_max);

        $this->withProgressBar($range, function ($parcel_id) use ($municipality_id, $file_name, $file) {
            $entry = $this->getEntry($parcel_id, $municipality_id);

            $owners = $this->getOwners($entry);
            $owners->each(fn($owner) => fputcsv($file, [$parcel_id, $municipality_id, $owner]));

        });

        fclose($file);

        $this->newLine();
        $this->info('Completed ' . Carbon::now()->diffForHumans($start) . ' start');


        return self::SUCCESS;
    }

    private function getOwners(DOMDocument $document): Collection
    {
        $classname='eigentum';
        $finder = new \DOMXPath($document);
        $nodes = $finder->query("//*[contains(@class, '$classname')]");

        $owners = collect();

        foreach ($nodes as $node) {
            if($node->nodeValue === 'laut Grundbuch') {
                continue;
            }

            $owners->push($node->nodeValue);
        }

        return $owners;

    }

    private function getEntry($parcel_id, int $municipality_id): DOMDocument
    {
        $response = Zttp::withCookies($this->cookie_jar)
            ->get("https://egov.ur.ch/teraspur/wsclient/WSgbausMSXML2.asp?ws=GBAUS&gruid=$parcel_id.$municipality_id&gb=$municipality_id&lang=D&asn=1&dar=&hist=");

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML($response->body());

        return $doc;
    }
}
