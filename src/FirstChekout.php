<?php

namespace Nero360\FirstChekout;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Validator;
use Nero360\FirstChekout\Exceptions\ValidationFailedException;

/**
*  This file is part of PHP FirstChekout package
*
*  Use this section to define what this class is doing, the PHPDocumentator will use this
*  to automatically generate an API documentation using this information.
*
*  @author Nero Okiewhru
*/
class FirstChekout{
    /**
     * A long string generated using sha512 hash algorithm of a concatenation of the transaction reference, merchant's secret and merchant's code.
     *
     * @var
     */
    private $hash;

    /**
     * Issued Merchant test secret from firstChekout dashboard
     *
     * @var string
     */
    protected $merchantTestSecret;

    /**
     * Issued Merchant live secret from firstChekout dashboard
     *
     * @var string
     */
    protected $merchantLiveSecret;

    private $merchantSecret;

    /**
     * Issued Merchant code from firstChekout dashboard
     *
     * @var string
     */
    protected $merchantCode;

    /**
     * Merchant test callback url
     *
     * @var string
     */
    protected $testCallbackUrl;

    /**
     * Merchant test callback url
     *
     * @var string
     */
    protected $liveCallbackUrl;

    private $callbackUrl;
    /**
     * System test mode, true or false
     *
     * @var string
     */
    private $testMode;

    /**
     * Test Payment URL as provided in FirstChekout documentation
     *
     * @var string
     */
    private $testPaymentUrl = "http://upperbox1.com:8082/pay";
//    private $testPaymentUrl = "https://sandbox.firstchekout.com/pay";

    /**
     * Live Payment URL as provided in FirstChekout documentation
     *
     * @var string
     */
    private $livePaymentUrl = "https://pay.firstchekout.com/pay";

    private $paymentUrl;

    /**
     * FirstChekout Base URI for bespoke services
     *
     * @var string
     */
    private $bespokeBaseUri = "https://bespoke.checkout.upltest.com/api/";
//    private $bespokeBaseUri = "https://bespoke.firstchekout.com/api/";

    /**
     * FirstChekout test transaction re-query URI
     *
     * @var string
     */
    private $testRequeryUri = "sandbox/transactions";

    /**
     * FirstChekout live transaction re-query URI
     *
     * @var string
     */
    private $liveRequeryUri = "transactions";

    private $requeryUrl;

    /**
     * FirstChekout Test Reversal URI
     *
     * @var string
     */
    private $testReversalUri = "sandbox/transactions/reversal";

    /**
     * FirstChekout Live Reversal URI
     *
     * @var string
     */
    private $liveReversalUri = "transactions/reversal";

    private $reversalUri;

    /**
     * FirstChekout Test Direct Debit URI
     *
     * @var string
     */
    private $testDirectDebitUri = "sandbox/transactions/direct/debit";

    /**
     * FirstChekout Live Direct Debit URI
     *
     * @var string
     */
    private $liveDirectDebitUri = "transactions/direct/debit";

    private $directDebitUri;

    /**
     * FirstChekout Settlement URI
     *
     * @var string
     */
    private $settlementUri = "transactions/settled";

    public function __construct()
    {
        $this->setMerchantTestSecret();
        $this->setMerchantLiveSecret();
        $this->setMerchantCode();
        $this->setTestCallbackUrl();
        $this->setLiveCallbackUrl();
        $this->setParameters();
    }

    public function setMerchantTestSecret()
    {
        $this->merchantTestSecret = config('firstChekout.test_secret');
    }

    public function setMerchantLiveSecret()
    {
        $this->merchantLiveSecret = config('firstChekout.live_secret');
    }

    public function setMerchantCode()
    {
        $this->merchantCode = config('firstChekout.code');
    }

    public function setTestCallbackUrl()
    {
        $this->testCallbackUrl = config('firstChekout.test_callback_url');
    }

    public function setLiveCallbackUrl()
    {
        $this->liveCallbackUrl = config('firstChekout.live_callback_url');
    }


    private function setParameters()
    {
        $this->testMode = (config('firstChekout.test_mode')) ? true : false;
        $this->merchantSecret = ($this->testMode) ? $this->merchantTestSecret : $this->merchantLiveSecret;
        $this->callbackUrl = ($this->testMode) ? $this->testCallbackUrl : $this->liveCallbackUrl;
        $this->paymentUrl = ($this->testMode) ? $this->testPaymentUrl : $this->livePaymentUrl;
        $this->reversalUri = ($this->testMode) ? $this->testReversalUri: $this->liveReversalUri;
        $this->directDebitUri = ($this->testMode) ? $this->testDirectDebitUri : $this->liveDirectDebitUri;

        $this->requeryUrl = $this->bespokeBaseUri . (($this->testMode) ? $this->testRequeryUri : $this->liveRequeryUri);
    }

    /**
     * Sanitize and generate form values
     *
     * @param array $options
     * @return array
     */
    public function raw(array $options = [])
    {
        $inputs['merchant_key'] = array_get($options, 'merchant_key', $this->merchantSecret);
        $inputs['redirect_url'] = array_get($options, 'redirect_url', $this->callbackUrl);
        $inputs['amount'] = array_get($options, 'amount');
        $inputs['description'] = array_get($options, 'description');
        $inputs['transaction_reference'] = array_get($options, 'transaction_reference', TransactionReference::getHashedToken(11));
        $inputs['email'] = array_get($options, 'email');
        $inputs['tokens[]'] = array_get($options, 'tokens[]');

        return $inputs;
    }

    /**
     * Create a new HTML form with sanitized values
     *
     * @param array $options
     * @param $id
     * @param bool $submitButton
     * @return string
     */
    public function form(array $options = [], $id = 'paymentForm', $submitButton = true)
    {
        $form = '<form action ="'.$this->paymentUrl.'" method="post" id="'.$id.'">'.PHP_EOL;

        foreach ($this->raw($options) as $key => $value) {
            $form .= '<input type="hidden" name="'.$key.'" value="'.$value.'" />'.PHP_EOL;
        }

        if($submitButton) {
            $form .= '<input type="submit" class="btn btn-primary"  name="submit" value="Pay" />'.PHP_EOL;
        }
        $form .= "</form>".PHP_EOL;
        
        return $form;
    }

    /**
     * Re-query firstChekout to confirm transaction status
     *
     * @param string $transaction_reference
     * @return mixed
     * @throws \Exception
     */
    public function reQuery($transaction_reference)
    {
        $this->generateHash($transaction_reference);

        return $this->bespokeService('GET', "{$this->requeryUrl}/{$transaction_reference}/$this->hash/query/");
    }

    /**
     * Direct debit for merchant to debit consumers having standing orders setup on their site.
     *
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public function directDebit(array $params)
    {

        $this->validate(Validator::make($params, [
            'transaction_reference' => 'required',
            'amount' => 'required|numeric',
            'narration' => 'required|string',
            'currency_code' => 'required|in:NGN',
            'email' => 'required|email',
            'signature' => 'required'
        ]));

        $this->generateHash($params['transaction_reference'], $params['amount']);

        return $this->bespokeService('POST', "{$this->directDebitUri}/{$this->hash}", $params);

    }

    /**
     * There are times things go wrong, customer needs to be refunded. A successful transaction can be reversed
     *
     * @param $transaction_reference
     * @return mixed
     */
    public function reversal($transaction_reference)
    {
        $this->generateHash($transaction_reference);
        return $this->bespokeService('POST', "{$this->reversalUri}/{$transaction_reference}/{$this->hash}");
    }

    /**
     * Merchant can always confirm if a transaction or list of transactions has been settled
     *
     * @param array $transaction_references
     * @return mixed
     */
    public function settlements(array $transaction_references)
    {
        $this->merchantMustBeLive();

        return $this->bespokeService('POST', $this->settlementUri, ['transaction_references' => $transaction_references]);
    }

    /**
     * @param $validator
     * @throws ValidationFailedException
     */
    private function validate($validator)
    {
        if ($validator->fails()) {
            $errors = [];

            foreach ($validator->messages()->getMessages() AS $field) {
                foreach ($field AS $message) {
                    $errors[] = $message;
                }
            }

            throw new ValidationFailedException("Validation failed because: " . join('; ', $errors));
        }
    }

    /**
     * Generates a long string using sha512 hash algorithm of a concatenation of the transaction reference, merchant's secret and merchant's code
     *
     * @param $transaction_reference
     * @param null $amount
     * @return string
     */
    private function generateHash($transaction_reference, $amount = null)
    {
        $this->hash = ($amount == null) ?
            hash('sha512', $transaction_reference . $this->merchantSecret . $this->merchantCode) :
            hash('sha512', $transaction_reference . $amount . $this->merchantSecret . $this->merchantCode);
        return $this;
    }

    /**
     * All bespoke services goes through this point
     *
     * @param $method
     * @param $uri
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    private function bespokeService($method, $uri, array $data = [])
    {
        $valid_methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        if(!in_array($method, $valid_methods)) throw new \Exception('Invalid method supplied in request');

        $client = new Client([
            'base_uri' => $this->bespokeBaseUri,
            'headers' => [
                'Content-Type' => 'application/json',
                'Api-Key' => $this->merchantSecret,
            ]
        ]);

        $options['json'] = (!empty($data)) ? ['data' => $data] : [] ;

        try {
            $response = $client->request($method, $uri, $options);

            if ($response->getStatusCode() === 200) {
                try {
                    $result = json_decode($response->getBody()->getContents());

                    if ($result->status) return $result->data;

                    throw new \Exception($result->message);

                } catch (\Exception $exception) {
                    throw $exception;
                }

            }

        } catch (\Exception $exception) {
            throw $exception;
        }
        throw new \Exception("An unknown problem occurred!");
    }

    /**
     * Ensures merchant is LIVE
     *
     * @throws \Exception
     */
    private function merchantMustBeLive()
    {
        if ($this->testMode) throw new \Exception('Service not available in test mode');
    }

}