<?php
class Comodo
{
    public function __construct()
    {

        $this->urls                         = new \stdClass();
        $this->urls->decode                 = 'https://secure.comodo.net/products/%21DecodeCSR';
        $this->urls->autoApplySsl           = 'https://secure.comodo.net/products/!AutoApplySSL';
        $this->urls->collectSsl             = 'https://secure.comodo.net/products/download/CollectSSL';
        $this->urls->sslChecker             = 'https://secure.comodo.net/sslchecker';

        $this->headers                   = new \stdClass();
        $this->headers->contentType      = 'Content-Type="application/' 
                                            . 'x-www-form-urlencoded"';
        $this->headers->userAgent        = '"User-Agent=Mozilla/5.0 (Windows' 
                                            . ' NT 6.1; Win64; x64; rv:47.0)' 
                                            . ' Gecko/20100101 Firefox/47.0"';

        $this->args                      = new \stdClass();
        $this->args->showCSR             = "N";
        $this->args->showErrorCodes      = "N";
        $this->args->showErrorMessages   = "Y";
        $this->args->showFieldNames      = "N";
        $this->args->showEmptyFields     = "N";
        $this->args->showCN              = "N";
        $this->args->showAddress         = "N";
        $this->args->showCSRHashes2      = "Y";
        $this->args->product             = "488";
        $this->args->years               = "1";
        $this->args->serverSoftware      = "22";
        $this->args->uniqueValue         = $this->randomString();
        $this->args->dcvMethod           = "HTTP_CSR_HASH";
        $this->args->isCustomerValidated = "Y";
        $this->args->test                = "Y";

        $this->creds                     = new \stdClass();
        $this->creds->path               = '/opt/dedrads/sslinstall';
        $this->creds->filename           = '/comodocreds.json';
        
        $strJsonFileContents             = file_get_contents(
                                            $this->creds->path 
                                            . $this->creds->filename);
        $this->credentials               = json_decode($strJsonFileContents);
    }

    public function randomString($length = 20)
    {

        $characters         = '0123456789abcdefghijklmnopqrstuvwxy' 
                                . 'zABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength   = strlen($characters);
        $randomString       = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString  .= $characters[rand(0, $charactersLength - 1)];
        }

        return strtoupper($randomString);
    }

    public function getCsrHashes($csr)
    {

        $argsArray = array(
            "loginName"             =>  $this->credentials->loginName,
            "loginPassword"         =>  $this->credentials->loginPassword,
            "showCSR"               =>  $this->args->showCSR,
            "showErrorCodes"        =>  $this->args->showErrorCodes,
            "showErrorMessages"     =>  $this->args->showErrorMessages,
            "showFieldNames"        =>  $this->args->showFieldNames,
            "showEmptyFields"       =>  $this->args->showEmptyFields,
            "showCN"                =>  $this->args->showCN,
            "showAddress"           =>  $this->args->showAddress,
            "showCSRHashes2"        =>  $this->args->showCSRHashes2,
            "product"               =>  $this->args->product,
            "csr"                   =>  $csr );
        
        $argsQuery              = http_build_query($argsArray);
        $callResult             = explode("\n", 
                                    $this->call([$this->urls->decode, 
                                                    $argsQuery, 
                                                    count($argsArray)
                                                    ]));
        $csrHashes              = new \stdClass();
        $csrHashes->md5         = ltrim($callResult[1], "md5=");
        $csrHashes->sha256      = ltrim($callResult[3], "sha256=");

        return $csrHashes;
    }

    public function orderSsl($csr)
    {

        $argsArray = array(
            "loginName"             =>  $this->credentials->loginName,
            "loginPassword"         =>  $this->credentials->loginPassword,
            "product"               =>  $this->args->product,
            "years"                 =>  $this->args->years,
            "serverSoftware"        =>  $this->args->serverSoftware,
            "uniqueValue"           =>  $this->args->uniqueValue,
            "dcvMethod"             =>  $this->args->dcvMethod,
            "isCustomerValidated"   =>  $this->args->isCustomerValidated,
            "test"                  =>  $this->args->test,
            "csr"                   =>  $csr );

        $argsQuery                  = http_build_query($argsArray);
        $callResult                 = explode("\n", 
                                        $this->call([$this->urls->autoApplySsl, 
                                                        $argsQuery,
                                                        count($argsArray)
                                                        ]));
        return $callResult[1];
    }

    public function collectSsl($SslOrder)
    {

        $argsArray = array(
            "loginName"             =>  $this->credentials->loginName,
            "loginPassword"         =>  $this->credentials->loginPassword,
            "queryType"             =>  1,
            "responseEncoding"      =>  0,
            "responseFormat"        =>  0,
            "responseType"          =>  3,
            "orderNumber"           =>  $SslOrder );

        $argsQuery                  = http_build_query($argsArray);
        $callResult                 = $this->call([$this->urls->collectSsl,
                                                        $argsQuery,
                                                        count($argsArray)
                                                        ]);

        $resultArray                = explode("\n", $callResult);
        
        $x = 0;
        while (intval($resultArray[0]) == 0) {

            if (x == 10){
                die("\nFailed to Obtain SSL Certificate after 10 tries\n");
            }

            echo "\nWaiting for Comodo to Process Order # " . $SslOrder 
                . " ...\n";
            sleep (30);
            $callResult             = $this->call([$this->urls->collectSsl,
                                                        $argsQuery,
                                                        count($argsArray)
                                                        ]);

            $resultArray            = explode("\n", $callResult);

            $x = $x + 1;
        }

        if (intval($resultArray[0]) < 0) {

            echo "\nFailed to Obtain Comodo SSL Certificate\n";
            
            die("\n" . $resultArray[1] . "\n");
        }

        if (intval($resultArray[0]) == 2) {

            echo "\nSuccessfully Obtained Comodo SSL Certificate\n";
            
            $resultArray                = explode("-----BEGIN CERTIFICATE-----",                                $callResult);
            $resultObj                  = new \stdClass();
            $resultObj->responseCode    = $resultArray[0];
            $resultObj->caCert          = "-----BEGIN CERTIFICATE-----"
                                            . $resultArray[1];
            $resultObj->cert            = "-----BEGIN CERTIFICATE-----"
                                            . $resultArray[2];
            
            return $resultObj;
        }
    }

    public function sslChecker($domain, $sha256)
    {
        
        $argsArray      = array("url" => $domain);

        sleep(10);

        $argsQuery      = http_build_query($argsArray);
        $callResult     = explode("\n", 
                            $this->call([$this->urls->sslChecker,
                                            $argsQuery,
                                            count($argsArray)
                                            ]));

        parse_str($callResult[0], $output);
        
        if(trim($output['cert_fingerprint_sha256']) == trim($sha256)) {
            echo "\nSSL Installation Verified by SSL Checker\n";

        } else {
            die("SSL Installation Failure\n\tInstalled Certificate does not Match purchased Certificate\n");
        }

        return $output;
    }

    public function call($argsArray)
    {

        $ch = curl_init();
        curl_setopt($ch,    CURLOPT_URL,                $argsArray[0]);
        curl_setopt($ch,    CURLOPT_POST,               true);
        curl_setopt($ch,    CURLOPT_POSTFIELDS,         $argsArray[1]);
        curl_setopt($ch,    CURLOPT_RETURNTRANSFER,     1);

        $result = curl_exec($ch);
        
        curl_close($ch);

        return $result;
    }
    
}
