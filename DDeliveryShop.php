<?php
/**
 * User: DnAp
 * Date: 14.05.14
 * Time: 10:42
 */
use DDelivery\Order\DDeliveryProduct;

class DDeliveryShop extends \DDelivery\Adapter\PluginFilters
{
    protected $config;
    protected $itemList;
    protected $formData;
    protected $orderProps = null;

    /**
     * @param array $config
     * @param array $itemList
     * @param $formData
     */
    public function __construct($config, $itemList, $formData)
    {
        $this->itemList = $itemList;
        $this->config = $config;
        $this->formData = $formData;

    }

    protected function getOrderProps()
    {
        if($this->orderProps === null) {
            $this->orderProps = array();
            if(isset($this->formData["PERSON_TYPE"])) {
                $db_props = CSaleOrderProps::GetList( array(),
                    array(
                        "PERSON_TYPE_ID" => $this->formData["PERSON_TYPE"],
                    )
                );

                while($prop = $db_props->Fetch()){
                    $this->orderProps[] = $prop;
                }
            }
        }
        return $this->orderProps;

    }

    protected function config($key)
    {
        if(isset($this->config[$key])){
            if(isset($this->config[$key]['VALUE'])) {
                return $this->config[$key]['VALUE'];
            }elseif(isset($this->config[$key]['DEFAULT'])) {
                return isset($this->config[$key]['DEFAULT']);
            }
        }
        return  null;
    }

    /**
     * ������� true ���� ����� ������������ ��������(stage) ������
     * @return bool
     */
    public function isTestMode()
    {
        return (bool)$this->config('TEST_MODE');
    }

    /**
     * ������������ � utf8 ������, ���� � �������� �� ������� UTF8
     * @param string[]|string $string
     * @return string[]|string
     */
    static private function toUtf8($string)
    {
        if(defined('BX_UTF')){
            return $string;
        }
        return \Bitrix\Main\Text\Encoding::convertEncodingArray($string, 'CP1251', 'UTF-8');
    }

    /**
     * ���������� ������ ����������� � ������� ������������, ����� ������ ���� ���, ����� �����������
     * @return DDeliveryProduct[]
     */
    protected function _getProductsFromCart()
    {
        $productsDD = array();
        $iblockElIds = array();

        foreach($this->itemList as $item) {
            $iblockElIds[] = $item['PRODUCT_ID'];
        }

        $rsProducts = CCatalogProduct::GetList(
            array(),
            array('ID' => $iblockElIds),
            false,
            false,
            array('ID', 'ELEMENT_IBLOCK_ID', 'WIDTH', 'HEIGHT', 'LENGTH', 'WEIGHT')
        );
        $productList = array();
        while ($arProduct = $rsProducts->Fetch()){
            $productList[$arProduct['ID']] = $arProduct;
        }

        foreach($this->itemList as $item) {
            $product = $productList[$item['PRODUCT_ID']];
            $iblock = $product['ELEMENT_IBLOCK_ID'];

            $elProperty  = array(
                'WIDTH' => $this->config('IBLOCK_'.$iblock.'_X'),
                'HEIGHT' => $this->config('IBLOCK_'.$iblock.'_Y'),
                'LENGTH' => $this->config('IBLOCK_'.$iblock.'_Z'),
                'WEIGHT' => $this->config('IBLOCK_'.$iblock.'_W'),
            );
            $size = array(
                'WIDTH' => $this->config('DEFAULT_X'),
                'HEIGHT' => $this->config('DEFAULT_Y'),
                'LENGTH' => $this->config('DEFAULT_Z'),
                'WEIGHT' => $this->config('DEFAULT_W'),
            );

            if($elProperty['WIDTH'] || $elProperty['LENGTH'] || $elProperty['HEIGHT'] || $elProperty['WEIGHT']) {
                $iblockElPropDB = CIBlockElement::GetProperty($iblock, $item['PRODUCT_ID'], array(), array('ID' => array_values($elProperty)));
                while($iblockElProp = $iblockElPropDB->Fetch()) {
                    foreach($elProperty as $k => $v) {
                        if($iblockElProp['ID'] == $v) {
                            $size[$k] = $iblockElProp['VALUE'];
                        }
                    }
                }
            }

            foreach($elProperty as $k => $v) {
                if(!$v && !empty($product[$k])){
                    $size[$k] = $product[$k];
                }
            }

            $productsDD[] = new DDeliveryProduct(
                $item['PRODUCT_ID'],	//	int $id id ������ � ������� �-��� ��������
                $size['WIDTH']/10,	//	float $width ������
                $size['HEIGHT']/10,	//	float $height ������
                $size['LENGTH']/10,	//	float $length ������
                $size['WEIGHT']/100,	//	float $weight ��� ��
                $item['PRICE'],	//	float $price ���������� ������
                $item['QUANTITY'],	//	int $quantity ���������� ������
                $this->toUtf8($item['NAME'])	//	string $name �������� ����
            );
        }
        return $productsDD;
    }

    /**
     * ������ ������ ����������� ������ cms
     *
     * @param $cmsOrderID - id ������
     * @param $status - ������ ������ ��� ����������
     *
     * @return bool
     */
    public function setCmsOrderStatus($cmsOrderID, $status)
    {
        // TODO: Implement setCmsOrderStatus() method.
    }

    /**
     * ���������� API ����, �� ������ �������� ��� ��� ������ ���������� � ������ ��������
     * @return string
     */
    public function getApiKey()
    {
        return $this->config('API_KEY');
    }

    /**
     * ������ ������� url �� �������� � ��������
     * @return string
     */
    public function getStaticPath()
    {
        return '/bitrix/components/ddelivery/static/';
    }

    /**
     * URL �� ������� ��� ���������� DDelivery::render
     * @return string
     */
    public function getPhpScriptURL()
    {
        // ������ �� ����� �����
        return '/bitrix/components/ddelivery/static/ajax.php?'.http_build_query(array('formData'=>$this->formData), "", "&");
    }

    /**
     * ���������� ���� �� ����� ���� ������, �������� ��� � ����� �� ��������� �� ������ ������
     * @return string
     */
    public function getPathByDB()
    {
        return __DIR__.'/db.sqlite';
    }

    /**
     * ����� ����� ������ ����� ������������ �������� ����� ������� ��������
     *
     * @param int $orderId
     * @param \DDelivery\Order\DDeliveryOrder $order
     * @param bool $customPoint ���� true, �� ����� �������������� ���������
     * @return void
     */
    public function onFinishChange($orderId, \DDelivery\Order\DDeliveryOrder $order, $customPoint)
    {
        if($customPoint){
            // ��� ������� ������� � ��� ��� ����� ������������ ����� ���������� CMS
        }else{
            // ������� id ������
        }
        $_SESSION['DIGITAL_DELIVERY']['ORDER_ID'] = $orderId;

    }

    /**
     * ����� ������� �� ��������� ����������
     * @return float
     */
    public function getDeclaredPercent()
    {
        return $this->config('DECLARED_PERCENT');
    }

    /**
     * ������ ������� �� �������� ������� �� ������������ � ��������
     * ��. ������ �������� � DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointCourier()
    {
        $result = array();
        foreach($this->config as $name => $data) {
            if(substr($name, 0, 8) == 'COMPANY_' && $data['VALUE'] == 'N'){
                $result[] = (int)substr($name, 8);
            }
        }
        return $result;
    }

    /**
     * ������ ������� �� �������� ������� �� ������������ � ����������
     * ��. ������ �������� � DDeliveryUI::getCompanySubInfo()
     * @return int[]
     */
    public function filterCompanyPointSelf()
    {
        $result = array();
        foreach($this->config as $name => $data) {
            if(substr($name, 0, 8) == 'COMPANY_' && $data['VALUE'] == 'N'){
                $result[] = (int)substr($name, 8);
            }
        }
        return $result;
    }

    /**
     * ���������� ������ ������ ���������� PluginFilters::PAYMENT_, ���������� ��� ������ �� �����. ������
     * @return int
     */
    public function filterPointByPaymentTypeCourier()
    {
        return self::PAYMENT_POST_PAYMENT;
        // �������� ���� �� 3 ���������(�� ������������ ��� �������� � ���������)
        return self::PAYMENT_POST_PAYMENT;
        return self::PAYMENT_PREPAYMENT;
        return self::PAYMENT_NOT_CARE;
        // TODO: Implement filterPointByPaymentTypeCourier() method.
    }

    /**
     * ���������� ������ ������ ���������� PluginFilters::PAYMENT_, ���������� ��� ������ �� �����. ���������
     * @return int
     */
    public function filterPointByPaymentTypeSelf()
    {
        return self::PAYMENT_POST_PAYMENT;
        // �������� ���� �� 3 ���������(�� ������������ ��� �������� � ���������)
        return self::PAYMENT_POST_PAYMENT;
        return self::PAYMENT_PREPAYMENT;
        return self::PAYMENT_NOT_CARE;
        // TODO: Implement filterPointByPaymentTypeSelf() method.
    }

    /**
     * ���� true, �� �� ��������� ���� ������
     * @return bool
     */
    public function isPayPickup()
    {
        return $this->config('PAY_PICKUP') == 'Y';
    }

    /**
     * ����� ���������� ��������� ������ ������� ������� ������ ���� ������� �� �������
     *
     * @return array
     */
    public function getIntervalsByPoint()
    {
        $return = array();
        for($i=1 ; $i<=3 ; $i++) {
            $return[] = array(
                'min' => $this->config('PRICE_IF_'.$i.'_MIN'),
                'max' => $this->config('PRICE_IF_'.$i.'_MAX'),
                'type' => $this->config('PRICE_IF_'.$i.'_TYPE'),
                'amount' => $this->config('PRICE_IF_'.$i.'_AOMUNT'),
            );
        }
        return $return;
    }

    /**
     * ��� ����������
     * @return int
     */
    public function aroundPriceType()
    {
        switch($this->config('AROUND')) {
            case 2:
                return self::AROUND_FLOOR;
            case 3:
                return self::AROUND_CEIL;
            case 1:
            default:
                return self::AROUND_ROUND;
        }
    }

    /**
     * ��� ����������
     * @return float
     */
    public function aroundPriceStep()
    {
        return (float) $this->config('AROUND_STEP');
    }

    /**
     * �������� ����������� ����� ��������
     * @return string
     */
    public function getCustomPointsString()
    {
        return '';
    }

    public function getCourierRequiredFields()
    {
        return parent::getCourierRequiredFields() & ~ self::FIELD_EDIT_LAST_NAME;
    }

    /**
     * ���� �� ������ ��� ����������, �������� ����� ��� ��������� � ���� ������
     * @return string|null
     */
    public function getClientFirstName() {
        foreach($this->getOrderProps() as $prop){
            if($prop['IS_PROFILE_NAME'] == 'Y' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                return $this->formData['ORDER_PROP_'.$prop['ID']];
            }
        }
        return null;
    }

    /**
     * ���� �� ������ ������� ����������, �������� ����� ��� ��������� � ���� ������
     * @return string|null
     */
    public function getClientLastName() {
        return null;
    }

    /**
     * ���� �� ������ ������� ����������, �������� ����� ��� ��������� � ���� ������. 11 ��������, �������� 79211234567
     * @return string|null
     */
    public function getClientPhone() {
        foreach($this->getOrderProps() as $prop){
            if($prop['CODE'] == 'PHONE' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                $phone = preg_replace('/[^0-9]/', '', $this->formData['ORDER_PROP_'.$prop['ID']]);
                if(strlen($phone) && $phone{0} == '8') {
                    $phone{0} = 7;
                }
                return $phone;
            }
        }
        return null;
    }

    /**
     * ����� ������ �����, ���, ������, ��������. ���� �� ������ ����� ������� ��� � ����� ���� � ��������� ����� get*RequiredFields
     * @return string[]
     */
    public function getClientAddress() {
        $return = array(array());
        foreach($this->getOrderProps() as $prop){
            if($prop['IS_LOCATION'] == 'Y' && !empty($this->formData['ORDER_PROP_'.$prop['ID'].'_val'])) {
                $return[0][0] = $this->formData['ORDER_PROP_'.$prop['ID'].'_val'];
            }
            if($prop['CODE'] == 'CITY' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                $return[0][1] = $this->formData['ORDER_PROP_'.$prop['ID']];
            }
            if($prop['CODE'] == 'ADDRESS' && !empty($this->formData['ORDER_PROP_'.$prop['ID']])) {
                $return[0][2] = $this->formData['ORDER_PROP_'.$prop['ID']];
            }
        }
        $return[0] = implode(' ', $return[0]);

        return $return;
    }

    /**
     * ������� id ������ � ������� DDelivery
     * @return int
     */
    public function getClientCityId()
    {
        // ���� ��� ���������� � ������, �������� ����� ������������� ������.
        return parent::getClientCityId();
    }

    /**
     * ���������� �������������� ��������� ������� ��������
     * @return array
     */
    public function getSupportedType()
    {
        switch($this->config('SUPPORTED_TYPE')) {
            case 1:
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
                );
            case 2:
                return array(
                    \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER
                );
        }
        return array(
            \DDelivery\Sdk\DDeliverySDK::TYPE_COURIER,
            \DDelivery\Sdk\DDeliverySDK::TYPE_SELF
        );
    }


}