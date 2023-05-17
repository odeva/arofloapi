<?php

namespace App;

//use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Log\LogManager;
use Illuminate\Config\Repository;

class ArofloApi
{
    public $status;
    public $statusMessage;
    public $maxPageResults;
    public $pageNumber;
    public $queryResponseTimes;
    public $currentPageResults;
    public $curl;

    public function __construct()
    {
        $this->curl = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function hasMorePages()
    {
        return($this->currentPageResults && $this->maxPageResults && $this->currentPageResults
            >= $this->maxPageResults);
    }

    public function wait($waitsecs = 0.5)
    {
        /*
        DB::transaction(function() use ($waitsecs) {
//            $prefix = DB::getTablePrefix();
//            DB::setTablePrefix('hms2a_');
            $select = DB::table('settings')->where('id', 1)->lockForUpdate()->first([
                'lastaroflotime']);
            var_dump(microtime(true));
            var_dump((new \DateTime($select->lastaroflotime ?? null))->format('U.u'));
            if (empty($select->lastaroflotime)) {
                DB::table('settings')->insert(['id' => 1, 'lastaroflotime' => DB::raw('NOW(6)')]);
                $secs = 0.0;
            } else
                    $secs = (microtime(true) - (float) (new \DateTime($select->lastaroflotime))->format('U.u'))/1000000.0;
            var_dump($secs);
            if ($waitsecs > $secs) {
                $sleepmicroseconds = 1000000.0 * ($waitsecs - $secs);
                usleep($sleepmicroseconds);
                logger()->info("pid: ".getmypid()." is sleeping for $sleepmicroseconds microseconds.");
            }
            DB::table('settings')->where('id', 1)->update(['lastaroflotime' => DB::raw('NOW(6)')]);
//            DB::setTablePrefix($prefix);
        });
         */
        usleep($waitsecs*1000000);
    }

    public function get($zone, $vars = [])
    {
        return $this->call($zone, $vars, 'GET');
    }

    public function post($zone, $vars = [])
    {
        return $this->call($zone, $vars, 'POST');
    }

    protected function call($zone, $vars = [], $method = 'GET')
    {
        $vars['zone']    = $zone;
        $urlPath         = '';
        $accept          = 'text/json';
        $authString      = 'uencoded='.urlencode(config('aroflo.apiauth.uEncoded')).'&pencoded='.urlencode(config('aroflo.apiauth.pEncoded')).'&orgEncoded='.urlencode(config('aroflo.apiauth.orgEncoded'));
        $isoTimeStamp    = gmdate('Y-m-d\TH:i:s.v\Z');
        $varString       = http_build_query($vars);
        $payload         = [
            $method,
//			$HostIP,
            '', //$urlPath
            $accept,
            $authString,
            $isoTimeStamp,
            $varString
        ];
        $afHmacSignature = hash_hmac('sha512', implode('+', $payload), config('aroflo.apiauth.secret'));
        $extraHeaders    = [
            "Authentication: HMAC $afHmacSignature",
//            "HostIP: XXX.XXX.XXX.XXX",
            "Authorization: $authString",
            "Accept: $accept",
            "afdatetimeutc: $isoTimeStamp"
        ];
        if ($method === 'POST')
                $extraHeaders[]  = "Content-Length: ".strlen($varString);
//        $curl            = curl_init();
        curl_reset($this->curl);
        curl_setopt_array($this->curl, [
            CURLOPT_URL => config('aroflo.apiurl').($method === 'GET' ? '?'.$varString
                    : ''),
            CURLOPT_RETURNTRANSFER => true,
//            CURLOPT_BINARYTRANSFER => true,
//            CURLOPT_ENCODING => "",
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => $extraHeaders,
            CURLOPT_HEADER => true,
            CURLINFO_HEADER_OUT => true,
//            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        if ($method === 'POST') {
            curl_setopt($this->curl, CURLOPT_POST, true);
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $varString);
        }

        $this->wait();
        $response   = curl_exec($this->curl);
        $curlError  = curl_error($this->curl);
        $headerSize = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $header     = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);
//        curl_close($curl);

        $now  = time();
        $path = 'logs'.DIRECTORY_SEPARATOR.'api';
        if (!File::isDirectory(storage_path($path)))
                File::makeDirectory(storage_path($path));
        $path .= DIRECTORY_SEPARATOR.date("Y", $now);
        if (!File::isDirectory(storage_path($path)))
                File::makeDirectory(storage_path($path));
        $path .= DIRECTORY_SEPARATOR.date("m", $now);
        if (!File::isDirectory(storage_path($path)))
                File::makeDirectory(storage_path($path));
        $path .= DIRECTORY_SEPARATOR.date("d", $now);
        if (!File::isDirectory(storage_path($path)))
                File::makeDirectory(storage_path($path));
        File::append(storage_path($path.DIRECTORY_SEPARATOR.'log_'.date("Ymd_Hi", $now)."_$zone.log"), date("[Y-m-d H:i:s] ", $now).urldecode($varString).PHP_EOL.print_r($response, true).PHP_EOL);

        if ($curlError) {
            logger()->error("CURL ERROR: $curlError.", compact('vars', 'response'));
            return false;
        } else {
            $r = json_decode($body);
            if (json_last_error()) {
                logger()->error('JSON ERROR: '.json_last_error().': '.json_last_error_msg().'.', compact('vars', 'response', 'header', 'body'));
                return false;
            } elseif (property_exists($r, 'status') && property_exists($r, 'statusmessage')
                && property_exists($r, 'zoneresponse')) {
                $this->status        = $r->status;
                $this->statusMessage = $r->statusmessage;
                $z                   = $r->zoneresponse;
                $zonename = strtolower($zone);
                if ($zonename === 'inventory') $zonename = 'items';
                if (property_exists($z, 'maxpageresults') && property_exists($z, 'pagenumber')
                    && property_exists($z, 'queryresponsetimes') && property_exists($z, $zonename)) {
                    $this->maxPageResults     = $z->maxpageresults;
                    $this->pageNumber         = $z->pagenumber;
                    $this->queryResponseTimes = $z->queryresponsetimes;
                    $this->currentPageResults = $z->currentpageresults;
                    $return = $z->$zonename;
                    $varString = null;
                    $payload = null;
                    $response = null;
                    $header = null;
                    $body = null;
                    $r = null;
                    $z = null;
                    return $return;
                } elseif (property_exists($z, 'postresults')) {
           			if (isset($z->postresults->errors) && !empty($z->postresults->errors)) {
                        logger()->error("ERROR RETURNED: An error was returned after posting data to Aroflo. ", compact('vars', 'response') );
//                        return false;
//                    } else {
//            			return $z->postresults;
                    }
                    $return = $z->postresults;
                    $varString = null;
                    $payload = null;
                    $response = null;
                    $header = null;
                    $body = null;
                    $r = null;
                    $z = null;
          			return $return;
                } else {
                    logger()->error('PARSE ERROR: Can\'t parse returning zoneresponse from Aroflo Api.', compact('vars', 'response'));
                    return false;
                }
            } else {
                logger()->error('PARSE ERROR: Can\'t parse returning response from Aroflo Api.', compact('vars', 'response'));
                return false;
            }
        }
    }

    public function getTransactionTerms($vars = [])
    {
        return($this->get('transactionterms', $vars));
    }

    public function getBusinessUnits($vars = [])
    {
        return($this->get('businessunits', $vars));
    }

    public function getClients($vars = [])
    {
        return($this->get('clients', $vars));
    }

    public function postClients($vars = [])
    {
        return($this->post('clients', $vars));
    }

    public function postClientsPostable($ids)
    {
        $postxml = new \SimpleXMLElement('<clients/>');
        foreach ($ids as $id) {
            $clientxml = $postxml->addChild('client');
            $clientxml->addChild('clientid', $id);
            $clientxml->addChild('postable', 'false');
        }
        $vars = [
            'postxml' => $postxml->asXML()
        ];
        return($this->postClients($vars));
    }

    public function getSuppliers($vars = [])
    {
        return($this->get('suppliers', $vars));
    }

    public function postSuppliers($vars = [])
    {
        return($this->post('suppliers', $vars));
    }

    public function postSuppliersPostable($ids)
    {
        $postxml = new \SimpleXMLElement('<suppliers/>');
        foreach ($ids as $id) {
            $supplierxml = $postxml->addChild('supplier');
            $supplierxml->addChild('supplierid', $id);
            $supplierxml->addChild('postable', 'false');
        }
        $vars = [
            'postxml' => $postxml->asXML()
        ];
        return($this->postSuppliers($vars));
    }


    public function getUsers($vars = [])
    {
        return($this->get('users', $vars));
    }

    public function getPriorities($vars = [])
    {
        return($this->get('priorities', $vars));
    }

    public function getLocations($vars = [])
    {
        return($this->get('locations', $vars));
    }

    public function getDocumentsAndPhotos($vars = [])
    {
        return($this->get('documentsandphotos', $vars));
    }

    public function getNotes($vars = [])
    {
        return($this->get('notes', $vars));
    }

    public function getTasktypes($vars = [])
    {
        return($this->get('tasktypes', $vars));
    }

    public function getCustomHolders($vars = [])
    {
        return($this->get('customholders', $vars));
    }

    public function getAssetCategories($vars = [])
    {
        return($this->get('assetcategories', $vars));
    }

    public function getAssets($vars = [])
    {
        return($this->get('assets', $vars));
    }

    public function postAssets($vars)
    {
        return($this->post('assets', $vars));
    }

    public function updateAssets($assets)
    {
        $postxml = new \SimpleXMLElement('<assets/>');
        foreach ($assets as $assetid => $asset) {
            $assetxml = $postxml->addChild('asset');
            $assetxml->addChild('assetid', $this->xmle($assetid));
            if (property_exists($asset, 'assetname')) $assetxml->addChild('assetname', $this->xmle($asset->assetname, 50));
            if (property_exists($asset, 'ordercode')) $assetxml->addChild('ordercode', $this->xmle($asset->ordercode, 50));
            if (property_exists($asset, 'customerid')) $assetxml->addChild('customerid', $this->xmle($asset->customerid, 50));
            if (property_exists($asset, 'modelnumber')) $assetxml->addChild('modelnumber', $this->xmle($asset->modelnumber, 50));
            if (property_exists($asset, 'serialnumber')) $assetxml->addChild('serialnumber', $this->xmle($asset->serialnumber, 100));
            if (property_exists($asset, 'barcode')) $assetxml->addChild('barcode', $this->xmle($asset->barcode, 100));
            if (property_exists($asset, 'manufacturer')) $assetxml->addChild('manufacturer', $this->xmle($asset->manufacturer, 50));
            if (property_exists($asset, 'supplier')) $assetxml->addChild('supplier', $this->xmle($asset->supplier, 50));
            if (property_exists($asset, 'odo')) $assetxml->addChild('odo', intval($asset->odo));
            if (property_exists($asset, 'odotype')) $assetxml->addChild('odotype', $this->xmle($asset->odotype, 10));
            if (property_exists($asset, 'cost')) $assetxml->addChild('cost', round($asset->cost, 4));
            if (property_exists($asset, 'quantity')) $assetxml->addChild('quantity', intval($asset->quantity));
            if (property_exists($asset, 'categoryid')) $assetxml->addChild('category')->addChild('categoryid', $this->xmle($asset->categoryid));
            if (property_exists($asset, 'locationid')) $assetxml->addChild('location')->addChild('locationid', $this->xmle($asset->locationid));
            if (property_exists($asset, 'customfields')) {
                $assetcustomfieldsxml = $assetxml->addChild('customfields');
                foreach ($asset->customfields as $cfkey => $cfval) {
                    $assetcfxml = $assetcustomfieldsxml->addChild('customfield');
                    $assetcfxml->addChild('name', $this->xmle($cfkey, 50));
                    $assetcfxml->addChild('type', 'text');
                    $assetcfxml->addChild('value', $this->xmle($cfval, 2000));
                }
            }
            if (property_exists($asset, 'archived')) $assetxml->addChild('archived', $this->xmle($asset->archived));
		}
        $vars = [
            'postxml' => $postxml->asXML()
        ];
        return($this->postAssets($vars));
//        return($vars['postxml']);
    }

    public function getInventoryCategories($vars = [])
    {
        return($this->get('inventorycategories', $vars));
    }

    public function getInventory($vars = [])
    {
        return($this->get('inventory', $vars));
    }

    public function getInventoryStockLevels($vars = [])
    {
        return($this->get('inventorystocklevels', $vars));
    }

    public function getInventoryLists($vars = [])
    {
        return($this->get('inventorylists', $vars));
    }

    public function getSubstatuses($vars = [])
    {
        return($this->get('substatuses', $vars));
    }

    public function getTasks($vars = [])
    {
        return($this->get('tasks', $vars));
    }

    public function postTasks($vars = [])
    {
//        if (!isset($vars['postxml'])) {
//            $postxml = new \SimpleXMLElement('<tasks/>');
//            $vars['postxml'] = $postxml->asXML();
//        }
        return($this->post('tasks', $vars));
    }

    public function postTaskMaterials($vars)
    {
        return($this->post('taskmaterials', $vars));
    }

    public function updateTaskMaterialsLinkProcessed($ids = [])
    {
        $postxml = new \SimpleXMLElement('<materials/>');
        foreach ($ids as $taskid => $lineids) {
            foreach ($lineids as $lineid => $processed) {
                $materialxml = $postxml->addChild('material');
                $materialxml->addChild('lineid', $lineid);
                $materialxml->addChild('matlinkprocessed', $processed ? 'true' : 'false');
                $taskxml = $materialxml->addChild('task');
                $taskxml->addChild('taskid', $taskid);
            }
        }
        $vars = [
            'postxml' => $postxml->asXML()
        ];
        return($this->postTaskMaterials($vars));
    }

    public function getQuotes($vars = [])
    {
        return($this->get('quotes', $vars));
    }

    public function postQuotes($vars = [])
    {
        return($this->post('quotes', $vars));
    }

    public function getPayments($vars = [])
    {
        return($this->get('payments', $vars));
    }

    public function getPurchaseorders($vars = [])
    {
        return($this->get('purchaseorders', $vars));
    }

    public function getPurchaseordersUsingPost($vars = [])
    {
        return($this->post('purchaseorders', $vars));
    }

    public function getInvoices($vars = [])
    {
        return($this->get('invoices', $vars));
    }

    public function getInvoicesUsingPost($vars = [])
    {
        return($this->post('invoices', $vars));
    }

    public function getBills($vars = [])
    {
        return($this->get('bills', $vars));
    }

    public function getBillsUsingPost($vars = [])
    {
        return($this->post('bills', $vars));
    }

    public function getLastupdate($vars = [])
    {
        return($this->get('lastupdate', $vars));
    }

    public function getLastupdateUsingPost($vars = [])
    {
        return($this->post('lastupdate', $vars));
    }

    public function xmle($text, $maxlen = null) {
        if ($maxlen) $t = substr($text, 0, $maxlen);
        else $t = $text;
        if ($t !== $text) logger()->warning("TEXT TRUNCATED: $text was truncated to $t to match maxlen of $maxlen");
    	return htmlentities($maxlen ? substr($text, 0, $maxlen) : $text, ENT_QUOTES | ENT_XML1);
    }

    public function rxmle($text, $encoding=null) {
    	if (isset($encoding)) return html_entity_decode($text, ENT_QUOTES | ENT_XML1, $encoding);
    	else return html_entity_decode($text, ENT_QUOTES | ENT_XML1);
    }

}