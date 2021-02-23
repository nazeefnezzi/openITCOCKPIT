<?php


namespace itnovum\openITCOCKPIT\Agent;


use App\Model\Entity\Agentconfig;
use App\Model\Table\AgentconfigsTable;
use App\Model\Table\ProxiesTable;
use Cake\ORM\TableRegistry;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use itnovum\openITCOCKPIT\Core\FileDebugger;

class AgentHttpClient {

    /**
     * Config array from \itnovum\openITCOCKPIT\Agent\AgentConfiguration
     * @var array
     */
    private $config = [];

    /**
     * @var Agentconfig
     */
    private $agentconfig;

    /**
     * @var string
     */
    private $hostaddress;

    /**
     * @var int
     */
    private $port;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * AgentHttpClient constructor.
     * @param Agentconfig $record
     * @param string $hostaddress
     */
    public function __construct(Agentconfig $agentconfig, string $hostaddress) {
        $this->agentconfig = $agentconfig;
        $AgentConfiguration = new AgentConfiguration();
        $this->config = $AgentConfiguration->unmarshal($agentconfig->config);

        $this->hostaddress = $hostaddress;
        $this->port = $this->config['int']['bind_port'];

        $protocol = 'https';
        // Is HTTP (plaintext) enabled?
        if ($this->config['bool']['use_autossl'] === false && $this->config['bool']['use_https'] === false) {
            $protocol = 'http';
        }

        // e.g.: https://127.0.0.1:3333
        $this->baseUrl = sprintf('%s://%s:%s', $protocol, $hostaddress, $this->port);
    }


    /**
     * Method that to the AutoTLS exchange for the Agent Wizard
     * @return array
     */
    public function testConnectionAndExchangeAutotls() {
        if ($this->config['bool']['use_autossl'] === true && $this->agentconfig->autossl_successful === true) {
            // Autossl is enabled AND the agent already successfully requested a certificate.
            // Check if an HTTPS connection is possible. If we can not connect via HTTPS try if the Agent is running with HTTP enabled
            // If openITCOCKPIT get an HTTP (no S!!) response from the agent someone deleted the certificate file on the Agent locally
            // Show a warning to the user with a button to generate a new certificate for this agent
            //
            // IF the Agent is only reachable via HTTPS than the certificate is fishy/compromised and we show an error that the user
            // have to manually delete the certificate files from the Agent and start the wizard again.
            // System may be compromised

            // Try to establish HTTPS connection
            $options = $this->getGuzzleOptions();
            $client = new Client();
            $url = sprintf('%s/autotls', $this->baseUrl);
            try {
                $response = $client->request('GET', $url, $options);
                if ($response->getStatusCode() === 200) {
                    $data = @json_decode($response->getBody()->getContents(), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['csr'])) {
                        // openITCOCKPIT communication to the Monitoring Agent is using secure AutoTLS certificates <3
                        return [
                            'status'       => 'success',
                            'error'        => __('Certificates where already exchanged.'),
                            'guzzle_error' => '',
                            'oitc_errno'   => AgentHttpClientErrors::ERRNO_OK
                        ];
                    }
                }

            } catch (RequestException | GuzzleException $e) {
                // HTTPS connection was not successfully

                $context = $e->getHandlerContext();
                if ($context['errno'] == 35) {
                    // cURL error 35: error:1408F10B:SSL routines:ssl3_get_record:wrong version number (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)

                    // Try HTTP (plaintext) connection to check if the certificate file got deleted on the agent
                    $options = $this->getGuzzleOptions();
                    unset($options['verify'], $options['cert'], $options['ssl_key']);
                    $url = sprintf('http://%s:%s/autotls', $this->hostaddress, $this->port);
                    $client = new Client();
                    try {
                        $response = $client->request('GET', $url, $options);

                        if ($response->getStatusCode() === 200) {
                            $data = @json_decode($response->getBody()->getContents(), true);
                            if (json_last_error() === JSON_ERROR_NONE && isset($data['csr'])) {
                                // Agent is running with HTTP
                                // Show warning to the user
                                return [
                                    'status'       => 'warning',
                                    'error'        => __('Agent is only reachable through HTTP but should be reachable through secure HTTPS. Most likely someone deleted the certificate file on the agent.'),
                                    'guzzle_error' => '',
                                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_AGENT_RESPONSES_TO_HTTP
                                ];
                            }
                        }
                    } catch (RequestException | GuzzleException $e) {
                        // Ignore
                    }
                }

                // On certificate mismatch we get:
                // cURL error 56: OpenSSL SSL_read: error:14094412:SSL routines:ssl3_read_bytes:sslv3 alert bad certificate, errno 0 (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
                // But we don't care about the exact error.
                return [
                    'status'       => 'error',
                    'error'        => __('TLS certificate mismatch. Agent has maybe compromised. Please make sure that you are connected to the right Agent. To resolve this issue delete the files "agent.crt", "agent.csr" and "server_ca-crt" and restart the Agent.'),
                    'guzzle_error' => $e->getMessage(),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_HTTPS_COMPROMISED
                ];
            }
        }

        if ($this->config['bool']['use_autossl'] === true && $this->agentconfig->autossl_successful === false) {
            // This Agent had never get a certificate. Try if the agent is running on HTTP and if yes
            // we POST a new certificate to the agent.
            // Once an Agent got a certificate - we never ever talk again with this agent using HTTP

            // Try to load CSR (Certificate Signing Request) from Agent via HTTP
            $options = $this->getGuzzleOptions();
            unset($options['verify'], $options['cert'], $options['ssl_key']);
            $url = sprintf('http://%s:%s/autotls', $this->hostaddress, $this->port);
            $client = new Client();
            try {
                $response = $client->request('GET', $url, $options);

                if ($response->getStatusCode() === 200) {
                    $data = @json_decode($response->getBody()->getContents(), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['csr'])) {
                        // Agent is running with HTTP
                        // Send new Certificate to Agent to enable AutoTLS

                        $AgentCertificateData = new AgentCertificateData();
                        try {
                            $options['json'] = [
                                'signed' => $AgentCertificateData->signAgentCsr($data['csr']),
                                'ca'     => $AgentCertificateData->getCaCert()
                            ];

                            $postResponse = $client->request('POST', $url, $options);
                            if ($postResponse->getStatusCode() === 200) {

                                // Save to the database that we successfully exchanged AutoTLS certificates
                                // so we will never ever talk to this agent using HTTP again !

                                /** @var AgentconfigsTable $AgentconfigsTable */
                                $AgentconfigsTable = TableRegistry::getTableLocator()->get('Agentconfigs');
                                $entity = $AgentconfigsTable->get($this->agentconfig->get('id'));
                                $entity->autossl_successful = true;
                                $AgentconfigsTable->save($entity);

                                return [
                                    'status'       => 'success',
                                    'error'        => __('AutoTLS certificates successfully exchanged.'),
                                    'guzzle_error' => '',
                                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_OK

                                ];
                            }
                            return [
                                'status'       => 'error',
                                'error'        => __('Error while sending certificate to Agent.'),
                                'guzzle_error' => sprintf('[%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                                'oitc_errno'   => AgentHttpClientErrors::ERRNO_EXCHANGE_HTTPS_CERTIFICATE
                            ];

                        } catch (RequestException | GuzzleException $e) {
                            return [
                                'status'       => 'error',
                                'error'        => __('Error while sending certificate to Agent.'),
                                'guzzle_error' => $e->getMessage(),
                                'oitc_errno'   => AgentHttpClientErrors::ERRNO_EXCHANGE_HTTPS_CERTIFICATE
                            ];
                        }
                    }
                    //Agent did not returned JSON or no 'csr' key in json
                    return [
                        'status'       => 'error',
                        'error'        => __('No JSON response from Agent or key csr is missing in the result'),
                        'guzzle_error' => sprintf('[%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                        'oitc_errno'   => AgentHttpClientErrors::ERRNO_BAD_AGENT_RESPONSE
                    ];
                }
                // got != 200 from Agent
                return [
                    'status'       => 'error',
                    'error'        => __('Could not establish HTTP connection for AutoTLS certificate exchange.'),
                    'guzzle_error' => sprintf('[%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_BAD_AGENT_RESPONSE
                ];


            } catch (RequestException | GuzzleException $e) {
                return [
                    'status'       => 'error',
                    'error'        => __('Could not establish HTTP connection for AutoTLS certificate exchange.'),
                    'guzzle_error' => $e->getMessage(),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_BAD_AGENT_RESPONSE
                ];
            }

        }

        if ($this->config['bool']['use_https'] === true) {
            // Try to load CSR (Certificate Signing Request) from Agent via HTTPS
            // THIS IS JUST A CONNECTION TEST - WE DO NOT USE THE CSR BECAUSE THE USER WANT TO USE OWN CERTIFICATS
            $options = $this->getGuzzleOptions();
            $url = sprintf('%s/autotls', $this->baseUrl);
            $client = new Client();
            try {
                $response = $client->request('GET', $url, $options);
                if ($response->getStatusCode() === 200) {
                    $data = @json_decode($response->getBody()->getContents(), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['csr'])) {
                        return [
                            'status'       => 'success',
                            'error'        => __('Successfully establish HTTPS connection to the Agent (No AutoTLS).'),
                            'guzzle_error' => '',
                            'oitc_errno'   => AgentHttpClientErrors::ERRNO_OK

                        ];
                    }
                }
                //Agent did not returned JSON or no 'csr' key in json
                return [
                    'status'       => 'error',
                    'error'        => __('No JSON response from Agent or key csr is missing in the result'),
                    'guzzle_error' => sprintf('[%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_BAD_AGENT_RESPONSE
                ];
            } catch (RequestException | GuzzleException $e) {
                return [
                    'status'       => 'error',
                    'error'        => __('Could not establish HTTPS connection'),
                    'guzzle_error' => $e->getMessage(),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_HTTPS_ERROR
                ];
            }
        }

        if ($this->config['bool']['use_autossl'] === false && $this->config['bool']['use_https'] === false) {
            // User wants insecure HTTP :(
            $options = $this->getGuzzleOptions();
            $url = sprintf('%s/autotls', $this->baseUrl);
            $client = new Client();
            try {
                $response = $client->request('GET', $url, $options);
                if ($response->getStatusCode() === 200) {
                    $data = @json_decode($response->getBody()->getContents(), true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['csr'])) {
                        return [
                            'status'       => 'success',
                            'error'        => __('Connection established successfully using insecure HTTP (plaintext).'),
                            'guzzle_error' => '',
                            'oitc_errno'   => AgentHttpClientErrors::ERRNO_OK

                        ];
                    }
                }
                //Agent did not returned JSON or no 'csr' key in json
                return [
                    'status'       => 'success',
                    'error'        => __('No JSON response from Agent or key csr is missing in the result'),
                    'guzzle_error' => sprintf('[%s] %s', $response->getStatusCode(), $response->getReasonPhrase()),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_HTTP_ERROR
                ];
            } catch (RequestException | GuzzleException $e) {
                return [
                    'status'       => 'error',
                    'error'        => __('Could not establish insecure HTTP connection'),
                    'guzzle_error' => $e->getMessage(),
                    'oitc_errno'   => AgentHttpClientErrors::ERRNO_HTTP_ERROR
                ];
            }
        }

        return [
            'status'       => 'unknown',
            'error'        => __('Unknown error'),
            'guzzle_error' => '',
            'oitc_errno'   => AgentHttpClientErrors::ERRNO_UNKNOWN
        ];
    }

    /**
     * Get the Guzzle connection options for ALL requests to the openITCOCKPIT Monitoring Agent
     * @return array
     */
    public function getGuzzleOptions() {
        $options = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'verify'  => true,
            'proxy'   => [
                'http'  => false,
                'https' => false
            ],
            'timeout' => 30
        ];

        // Add Proxy options
        if ($this->config['bool']['use_proxy'] === true) {
            /** @var ProxiesTable $ProxiesTable */
            $ProxiesTable = TableRegistry::getTableLocator()->get('Proxies');
            $proxySettings = $ProxiesTable->getSettings();

            if ($proxySettings['enabled']) {
                $options['proxy'] = [
                    'http'  => sprintf('%s:%s', $proxySettings['ipaddress'], $proxySettings['port']),
                    'https' => sprintf('%s:%s', $proxySettings['ipaddress'], $proxySettings['port'])
                ];
            }
        }

        // HTTP Basic Auth
        if ($this->config['bool']['use_http_basic_auth'] === true) {
            $options['auth'] = [
                $this->config['string']['username'],
                $this->config['string']['password']
            ];
        }

        // Disable HTTPS cert validation?
        if ($this->config['bool']['use_https'] === true && $this->config['bool']['use_https_verify'] === false) {
            $options['verify'] = false;
        }

        // Use AutoTLS?
        if ($this->config['bool']['use_autossl'] === true) {
            /** @var AgentCertificateData $AgentCertificateData */
            $AgentCertificateData = new AgentCertificateData();

            // Disable hostname validation
            // cURL error 51: SSL: certificate subject name 'subject' does not match target host name '127.0.0.1' (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)
            //$options['verify'] = $AgentCertificateData->getCaCertFile();
            $options['verify'] = false;

            $options['cert'] = $AgentCertificateData->getCaCertFile();
            $options['ssl_key'] = $AgentCertificateData->getCaKeyFile();
        }

        return $options;
    }

}
