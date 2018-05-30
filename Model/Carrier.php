<?php
namespace Dc\Fastway\Model;

use Magento\Framework\Xml\Security;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\Result;

class Carrier extends AbstractCarrierOnline implements CarrierInterface
{
    /**
     * 识别code，当前是fastway
     */
    const CODE = 'fastway';

    /**
     * 国家代码
     */
    const COUNTRYCODE = 24;

    /**
     * 查询快递跟踪信息url
     */
    const TRACKINGEVENTS = '/tracktrace/detail/';

    /**
     * 查询快递费用url
     */
    const PSCLOOKUP = '/psc/lookup/';

    /**
     * @var string
     */
    protected $_code = self::CODE;

    /**
     * @var int
     */
    protected $country_code = self::COUNTRYCODE;

    /**
     * @var string
     */
    protected $tracking_events = self::TRACKINGEVENTS;

    /**
     * @var string
     */
    protected $psc_lookup = self::PSCLOOKUP;

    /**
     * Rate request data
     *
     * @var Magento\Quote\Model\Quote\Address\RateRequest
     */
    protected $_request;

    /**
     * Rate result data
     *
     * @var Magento\Shipping\Model\Rate\Result
     */
    protected $_result;

    /**
     * http请求
     *
     * @var \Magento\Framework\HTTP\ZendClientFactory
     */
    protected $_httpClientFactory;

    /**
     * 验证接口
     *
     * @var \Dc\Fastway\Api\ValidatorInterface
     */
    protected $validator;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Framework\HTTP\ZendClientFactory $_httpClientFactory,
        \Dc\Fastway\Api\ValidatorInterface $validator,
        array $data = []
    ) {
        $this->_httpClientFactory = $_httpClientFactory;
        $this->validator = $validator;
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
    }

    /**
     * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
     *
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        $this->_prepareShipmentRequest($request);
        $this->_mapRequestToShipment($request);
        $this->setRequest($request);
        return $this->_doRequest();
    }

    /**
     * 获取支持的运输方式
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('name'))];
    }

    /**
     * 查询快递跟踪信息
     *
     * @param string $trackings
     * @return Result|null
     */
    public function getTracking($trackings)
    {
        // 先判断单号规则
        if (!$this->validator->isValid($trackings)) {
            $this->showShipmentError($trackings);
        } else {
            // 请求api查询快递跟踪信息
            $this->_getTrackSumary($trackings);
        }
        return $this->_result;
    }

    /**
     * 根据快递单号获取跟踪信息
     *
     * @param string $trackings
     * @return string|null
     */
    protected function _getTrackSumary($trackings)
    {
        // 请求api查快递信息
        $gateway_url = $this->getConfigData('gateway_url');
        $api_key = $this->getConfigData('apikey');
        $url = $gateway_url . $this->tracking_events . rawurlencode($trackings) . '/' . $this->country_code . '.xml?api_key=' . $api_key;
        $responseBody = $this->_getCachedQuotes($url);
        if ($responseBody === null) {
            try {
                $client = $this->_httpClientFactory->create();
                $client->setUri($url);
                $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);
                $responseBody = $client->request()->getBody();
                $this->_setCachedQuotes($url, $responseBody);
            } catch (\Exception $e) {
                $responseBody = '';
            }
        }
        return $this->_parseXmlTrackingResponse($trackings, $responseBody);
    }

    /**
     * 解析查询结果
     *
     * @param string $trackings
     * @param object $responseBody
     */
    protected function _parseXmlTrackingResponse($trackings, $responseBody)
    {
        $resultArray = [];
        if (strlen(trim($responseBody)) > 0) {
            $xml = $this->parseXml($responseBody, 'Magento\Shipping\Model\Simplexml\Element');
            // 错误信息
            if (is_object($xml)) {
                if (strlen($xml->error) <= 0) {
                    $packageProgress = [];
                    // 生存快递跟踪信息
                    foreach ($xml->result->Scans->array->item as $item) {
                        $description = $item->StatusDescription;
                        $location = $item->Name;
                        // 获取时间，需要解析
                        $datetime = \DateTime::createFromFormat('d/m/Y H:i:s', $item->Date);
                        $deliverydate = $datetime->format('Y-m-d');
                        $deliverytime = $datetime->format('h:i:s');
                        $shipmentEventArray = [];
                        $shipmentEventArray['activity'] = $description;
                        $shipmentEventArray['deliverydate'] = (string)$deliverydate;
                        $shipmentEventArray['deliverytime'] = (string)$deliverytime;
                        $shipmentEventArray['deliverylocation'] = $location;
                        $packageProgress[] = $shipmentEventArray;
                    }
                    asort($packageProgress);
                    $progressData['progressdetail'] = $packageProgress;
                    $resultArray[$trackings] = $progressData;
                }
            }
        }
        // 处理结果
        if (!empty($resultArray)) {
            $result = $this->_trackFactory->create();
            foreach ($resultArray as $trackings => $data) {
                $tracking = $this->_trackStatusFactory->create();
                $tracking->setCarrier($this->_code);
                $tracking->setCarrierTitle($this->getConfigData('title'));
                $tracking->setTracking($trackings);
                $tracking->addData($data);
                $result->append($tracking);
            }
            $this->_result = $result;
        } else {
            $this->showShipmentError($trackings);
        }
    }

    /**
     * 处理错误信息
     *
     * @param string $trackings
     */
    protected function showShipmentError($trackings)
    {
        $result = $this->_trackFactory->create();
        $error = $this->_trackErrorFactory->create();
        $error->setCarrier($this->_code);
        $error->setCarrierTitle($this->getConfigData('title'));
        $error->setTracking($trackings);
        $error->setErrorMessage($this->getConfigData('specificerrmsg'));
        $result->append($error);
        $this->_result = $result;
    }

    /**
     * 是否可用
     *
     * @return bool
     */
    public function canCollectRates()
    {
        return parent::canCollectRates() && $this->getConfigData('apikey');
    }

    /**
     * 获取运费
     *
     * @param Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return \Magento\Shipping\Model\Rate\ResultFactory
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->canCollectRates()) {
            return $this->getErrorMessage();
        }
        $this->setRequest($request);
        $city = $this->_getCity();
        $postcode = $this->_getPostcode();
        $weight = $this->_getWeight();
        $params = [
            'city' => $city,
            'postcode' => $postcode,
            'weight' => $weight,
        ];
        // 先从缓存拿数据
        $responseBody = $this->_getCachedQuotes($params);
        if ($responseBody === null) {
            try {
                $responseBody = $this->_getQuotesFromServer($params);
                $this->_setCachedQuotes($params, $responseBody);
            } catch (\Exception $e) {
                $responseBody = '';
            }
        }
        return $this->_parseXmlResponse($responseBody);
    }

    /**
     * 获取默认数据
     * NOTE: 目前没有使用官方地址为发货地址，这个方法暂时没用
     *
     * @param string|int $origValue
     * @param string $pathToValue
     * @return string|int|null
     */
    protected function _getDefaultValue($origValue, $pathToValue)
    {
        if (!$origValue) {
            $origValue = $this->_scopeConfig->getValue(
                $pathToValue,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->getStore()
            );
        }

        return $origValue;
    }

    /**
     * 调用fastway接口查询费用
     *
     * @param array $params
     * @return string|null
     */
    protected function _getQuotesFromServer($params)
    {
        $city = $params['city'];
        $postcode = $params['postcode'];
        $weight = $params['weight'];
        if (strlen($city) > 0 && strlen($postcode) > 0 && $weight > 0) {
            $client = $this->_httpClientFactory->create();
            $gateway_url = $this->getConfigData('gateway_url');
            $api_key = $this->getConfigData('apikey');
            $delivery_area = $this->getConfigData('delivery_area');
            // 地址、国家代码等使用配置设置
            $url = $gateway_url . $this->psc_lookup . $delivery_area . '/' . rawurlencode($city) . '/' . $postcode . '/1.xml?api_key=' . $api_key;
            $client->setUri($url);
            $client->setConfig(['maxredirects' => 0, 'timeout' => 30]);
            // 状态码判断，为200才是正确的返回结果
            $status = $client->request()->getStatus();
            if ($status === 200) {
                return $client->request()->getBody();
            }
        }
        return "";
    }

    /**
     * 购物车里的重量
     *
     * @return int
     */
    protected function _getWeight()
    {
        return ceil($this->_request->getPackageWeight());
    }

    /**
     * 收货地址城市
     *
     * @return string|null
     */
    protected function _getCity()
    {
        // 使用trim()函数去除前后空格
        return trim($this->_request->getDestCity());
    }

    /**
     * 收货地址邮编
     *
     * @return string|null
     */
    protected function _getPostcode()
    {
        return $this->_request->getDestPostcode();
    }

    /**
     * 解析返回结果xml
     *
     * @param $response
     * @return \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected function _parseXmlResponse($response)
    {
        $weight = $this->_getWeight();
        // fastway30kg内是同一价格
        $base_weight = ceil($weight / 30);
        $priceArray = [];
        // try catch 有错误时直接返回错误
        try {
            if (strlen(trim($response)) > 0) {
                $xml = $this->parseXml($response, 'Magento\Shipping\Model\Simplexml\Element');
                if (is_object($xml)) {
                    if (strlen($xml->error) == 0) {
                        $price = 0;
                        foreach ($xml->result->services->array->item as $item) {
                            $temp = $item->totalprice_frequent;
                            if ($temp > $price) {
                                $price = $temp;
                                $priceArray[(string) $xml->result->delivery_timeframe_days . 'days'] = $this->getMethodPrice(
                                    // 重量基数乘价格
                                    (float) ($base_weight * (float) $item->totalprice_frequent),
                                    (string) $xml->result->delivery_timeframe_days . 'days'
                                );
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            $priceArray = [];
        }
        // 处理查询结果
        $result = $this->_rateFactory->create();
        if (empty($priceArray)) {
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            $result->append($error);
        } else {
            // 倒序，最后的结果排在前面
            asort($priceArray);
            foreach ($priceArray as $method => $price) {
                $rate = $this->_rateMethodFactory->create();
                $rate->setCarrier($this->_code);
                $rate->setCarrierTitle($this->getConfigData('title'));
                $rate->setMethod($method);
                $rate->setMethodTitle($method);
                $rate->setCost($price);
                $rate->setPrice($price);
                $result->append($rate);
            }
        }
        return $result;
    }

    /**
     * 设置当前请求线程
     *
     * @param RateRequest $request
     * @return $this
     */
    public function setRequest(RateRequest $request)
    {
        $this->_request = $request;
        return $this;
    }

    /**
     * 处理过程
     *
     * @param \Magento\Framework\DataObject $request
     * @return $this
     */
    public function proccessAdditionalValidation(\Magento\Framework\DataObject $request)
    {
        return $this;
    }
}
