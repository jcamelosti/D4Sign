<?php

class Doc4Sign
{
    private $url = 'https://sandbox.d4sign.com.br/api/';
    private $accessToken;
    private $cryptKey;

    private $timeout = 240;
    private $version = "v1";

    /**
     * Get the value of url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set the value of url
     *
     * @return  self
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get the value of accessToken
     */
    public function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * Set the value of accessToken
     *
     * @return  self
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    /**
     * Get the value of cryptKey
     */
    public function getCryptKey()
    {
        return $this->cryptKey;
    }

    /**
     * Set the value of cryptKey
     *
     * @return  self
     */
    public function setCryptKey($cryptKey)
    {
        $this->cryptKey = $cryptKey;

        return $this;
    }

    /**
     * Get the value of timeout
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * Set the value of timeout
     *
     * @return  self
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Get the value of version
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Set the value of version
     *
     * @return  self
     */
    public function setVersion($version)
    {
        $this->version = $version;

        return $this;
    }

    protected function doRequest($url, $method, $data, $contentType = null)
    {
        $c = curl_init();

        $header = array("Accept: application/json");

        array_push($header, "tokenAPI: $this->accessToken");

        $url = $this->url . $this->version . $url . "?tokenAPI=" . $this->accessToken . "&cryptKey=" . $this->cryptKey;

        switch ($method) {
            case "GET":
                curl_setopt($c, CURLOPT_HTTPGET, true);
                if (count($data)) {
                    $url .= "&" . http_build_query($data);
                }
                break;

            case "POST":
                curl_setopt($c, CURLOPT_POST, true);
                if (count($data)) {
                    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
                }
                break;

            case "DELETE":
                curl_setopt($c, CURLOPT_CUSTOMREQUEST, $method);
                if ($data) {
                    curl_setopt($c, CURLOPT_POST, true);
                    curl_setopt($c, CURLOPT_POSTFIELDS, $data);
                }
                break;
        }


        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_TIMEOUT, $this->timeout);

        curl_setopt($c, CURLOPT_HTTPHEADER, $header);
        curl_setopt($c, CURLOPT_HEADER, true);

        curl_setopt($c, CURLOPT_URL, $url);
        curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($c);

        curl_close($c);

        return $response;
    }

    protected function request($url, $method, $data, $expectedHttpCode, $contentType = '')
    {

        $response = $this->doRequest($url, $method, $data, $contentType);

        return $this->parseResponse($url, $response, $expectedHttpCode);
    }

    protected function parseResponse($url, $response, $expectedHttpCode)
    {
        $header = false;
        $content = array();
        $status = 200;

        foreach (explode("\r\n", $response) as $line) {
            if (strpos($line, "HTTP/1.1") === 0 || strpos($line, "HTTP/2") === 0) {
                $lineParts = explode(" ", $line);
                $status = intval($lineParts[1]);
                $header = true;
            } else if ($line == "") {
                $header = false;
            } else if ($header) {
                $line = explode(": ", $line);
                if ($line[0] == "Status") {
                    $status = intval(substr($line[1], 0, 3));
                }
            } else {
                $content[] = $line;
            }
        }

        if ($status !== $expectedHttpCode) {
            throw new \Exception($content[0], 2);
        }

        $object = json_decode(implode("\n", $content));

        return $object;
    }

    protected function _getCurlFile($filename, $contentType = '', $postname = '')
    {
        // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
        // See: https://wiki.php.net/rfc/curl-file-upload
        if (function_exists('curl_file_create')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $finfo = finfo_file($finfo, $filename);

            return curl_file_create($filename, $finfo, basename($filename));
        }

        // Use the old style if using an older version of PHP
        $postname = $postname or $filename;
        $value = "@{$filename};filename=" . $postname;
        if ($contentType) {
            $value .= ';type=' . $contentType;
        } else {
            $value .= ';type=' . mime_content_type($filename);
        }

        return $value;
    }

    /*
    Upando o Arquivo para Ser Assinado
    */
    public function doUpload($uuid_safe, $filePath, $uuid_folder = '')
    {
        $f = $this->_getCurlFile($filePath);

        $data = array("file" => $f, "uuid_folder" => json_encode($uuid_folder));

        return $this->request("/documents/$uuid_safe/upload", "POST", $data, 200);
    }

    /*
    Atribuindo quem Vai Assinar o DOC    
    */
    public function createList($documentKey, $signers, $skipEmail = false)
    {
        $data = array("signers" => json_encode($signers));
        return $this->request("/documents/$documentKey/createlist", "POST", $data, 200);
    }

    /*
    Solicitando para que as Pessoas Atribuidas no Método createList assinem o doc
    */
    public function sendToSigner($documentKey, $message = '', $workflow = '0', $skip_email = false)
    {
    	$data = array("message" => json_encode($message), "workflow" => json_encode($workflow), "skip_email" => json_encode($skip_email));
    
    	return $this->request("/documents/$documentKey/sendtosigner", "POST", $data, 200);
    }
}


//Utilização da Classe
$client = new Doc4Sign();
$client->setUrl('https://sandbox.d4sign.com.br/api/'); //Desenvolvimento (SandBox)
$client->setAccessToken("live_64e03bcb93ca2a0f3ca0b45bdb3f7f4bd1bc9cf10c71844d951d828ec9aff7b8");
$client->setCryptKey("live_crypt_yAaa0GTKyFhZhjOOzufg563BkDO8ug1A");


//################Fazendo Upload de Arquivo
//$resposta = $client->doUpload('bd5ced9a-c480-4105-8a3e-775a3b74d448', 'Teste.pdf');
/*
stdClass Object ( [message] => success [uuid] => a4aa2ea3-8f61-47c7-95a2-2934b026b779 )
*/
//print_r($resposta->uuid); //9c1e1303-5e28-4b26-8cd8-253f00c0454c #ATENÇÃO O ID DO DOCUMENTO SERÁ USADO NO PROXIMO 2 METODOS





//################attribuindo quem vai assinar
/*$signers = array(
    array(
        "email" => "josueprg@gmail.com", 
        "act" => '1', 
        "foreign" => '0', 
        "certificadoicpbr" => '0', 
        "assinatura_presencial" => '0', 
        "embed_methodauth" => 'email', 
        "embed_smsnumber" => '+5562992527138'
    ),
    array(
        "email" => "contato@josuecamelo.com", 
        "act" => '1', 
        "foreign" => '0', 
        "certificadoicpbr" => '0', 
        "assinatura_presencial" => '0', 
        "embed_methodauth" => 'sms', 
        "embed_smsnumber" => '+5562984018589'
    )
);

$client->createList('9c1e1303-5e28-4b26-8cd8-253f00c0454c', $signers);*/


/*
    Enviando para ser Assinando de Fato
    Solicitação para quem foi atribuido no metodo anterior para que assinem o doc.
*/
//################enviar para Assinatura
/*$message = 'Prezados, segue o contrato eletrônico para assinatura.';
$workflow = 0; //Todos podem assinar ao mesmo tempo;
$skip_email = 1; //Não disparar email com link de assinatura (usando EMBED ou Assinatura Presencial);
$resposta = $client->sendToSigner('9c1e1303-5e28-4b26-8cd8-253f00c0454c', $message, $skip_email, $workflow);
print_r($resposta);*/