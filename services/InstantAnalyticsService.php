<?php
/**
 * Instant Analytics plugin for Craft CMS
 *
 * InstantAnalytics Service
 *
 * @author    nystudio107
 * @copyright Copyright (c) 2016 nystudio107
 * @link      http://nystudio107.com
 * @package   InstantAnalytics
 * @since     1.0.0
 */

namespace Craft;

use \TheIconic\Tracking\GoogleAnalytics\Analytics;
use \TheIconic\Tracking\GoogleAnalytics\Parameters\EnhancedEcommerce\ProductAction as ProductAction;

use \Jaybizzle\CrawlerDetect\CrawlerDetect;

class InstantAnalyticsService extends BaseApplicationComponent
{

    protected $cachedAnalytics = null;

    /**
     * Get the global variables for our Twig context
     * @return array with 'instantAnalytics' => Analytics object
     */
    public function getGlobals($title)
    {
        $result = array();

        if ($this->cachedAnalytics)
            $analytics = $this->cachedAnalytics;
        else
        {
            $analytics = $this->pageViewAnalytics("", $title);
            $this->cachedAnalytics = $analytics;
        }

/* -- Return our global variables */

        $result['instantAnalytics'] = $analytics;
        return $result;
    } /* -- getGlobals */

    /**
     * Get a PageView analytics object
     * @return Analytics object
     */
    public function pageViewAnalytics($url="", $title="")
    {
        $result = null;
        $analytics = $this->analytics();
        if ($analytics)
        {
            if ($url == "")
                $url = craft()->request->url;

/* -- We want to send just a path to GA for page views */

            if (UrlHelper::isAbsoluteUrl($url))
            {
                $urlParts = parse_url($url);
                if (isset($urlParts['path']))
                    $url = $urlParts['path'];
                else
                    $url = "/";
                if (isset($urlParts['query']))
                    $url = $url . "?" . $urlParts['query'];
            }

/* -- We don't want to send protocol-relative URLs either */

            if (UrlHelper::isProtocolRelativeUrl($url))
            {
                $url = substr($url, 1);
            }

/* -- Prepare the Analytics object, and send the pageview */

            $analytics->setDocumentPath($url)
                ->setDocumentTitle($title);
            $result = $analytics;
            InstantAnalyticsPlugin::log("Created sendPageView for `" . $url . "` - `" . $title . "`", LogLevel::Info, false);
        }
        return $result;
    } /* -- pageViewAnalytics */

    /**
     * Get an Event analytics object
     * @return Analytics object
     */
    public function eventAnalytics($eventCategory="", $eventAction="", $eventLabel="", $eventValue=0)
    {
        $result = null;
        $analytics = $this->analytics();
        if ($analytics)
        {
            $analytics->setEventCategory($eventCategory)
                ->setEventAction($eventAction)
                ->setEventLabel($eventLabel)
                ->setEventValue(intval($eventValue));
            $result = $analytics;
            InstantAnalyticsPlugin::log("Created sendEvent for `" . $eventCategory . "` - `" . $eventAction . "` - `" . $eventLabel . "` - `" . $eventValue . "`", LogLevel::Info, false);
        }
        return $result;
    } /* -- eventAnalytics */

    /**
     * getAnalyticsObject() return an analytics object
     * @return Analytics object
     */
    public function analytics()
    {
        $result = null;
        $analytics = $this->_getAnalyticsObj();
        $result = $analytics;
        InstantAnalyticsPlugin::log("Created genertic analytics object", LogLevel::Info, false);
        return $result;
    } /* -- analytics */

    /**
     * Get a PageView tracking URL
     * @param  string $url the URL to track
     * @param  string $title the page title
     * @return string the tracking URL
     */
    public function pageViewTrackingUrl($url, $title)
    {
        $urlParams = array(
            'url' => urlencode($url),
            'title' => urlencode($title),
            );
        $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
        $trackingUrl = UrlHelper::getSiteUrl('instantanalytics/pageViewTrack/' . $fileName, $urlParams);
        InstantAnalyticsPlugin::log("Created pageViewTrackingUrl for " . $trackingUrl, LogLevel::Info, false);
        return $trackingUrl;
    } /* -- pageViewTrackingUrl */

    /**
     * Get an Event tracking URL
     * @param  string $url the URL to track
     * @param  string $eventCategory the event category
     * @param  string $eventAction the event action
     * @param  string $eventLabel the event label
     * @param  string $eventValue the event value
     * @return string the tracking URL
     */
    public function eventTrackingUrl($url, $eventCategory="", $eventAction="", $eventLabel="", $eventValue=0)
    {
        $urlParams = array(
            'url' => urlencode($url),
            'eventCategory' => urlencode($eventCategory),
            'eventAction' => urlencode($eventAction),
            'eventLabel' => urlencode($eventLabel),
            'eventValue' => urlencode($eventValue),
            );
        $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
        $trackingUrl = UrlHelper::getSiteUrl('instantanalytics/eventTrack/' . $fileName, $urlParams);
        InstantAnalyticsPlugin::log("Created eventTrackingUrl for " . $trackingUrl, LogLevel::Info, false);
        return $trackingUrl;
    } /* -- eventTrackingUrl */

    /**
     * Extract product data from a Craft Commerce Product or Variant
     * @param Commerce_ProductModel or Commerce_VariantModel  $productVariant the Product or Variant
     * @return array the product data
     */
    public function getProductDataFromProduct($productVariant = null)
    {
        $result = array();
        if ($productVariant)
        {
            if (is_object($productVariant) && $productVariant->getElementType() == "Commerce_Product")
            {
                $productType = craft()->commerce_productTypes->getProductTypeById($productVariant->typeId);
                if ($productType->hasVariants)
                {
                    $productVariant = ArrayHelper::getFirstValue($productVariant->getVariants());
                    $product = $productVariant->getProduct();
                    if ($product)
                    {
                        $category = $product->getType()['name'];
                        $name = $product->title;
                        $variant = $productVariant->title;
                    }
                    else
                    {
                        $category = $productVariant->getType()['name'];
                        $name = $productVariant->title;
                        $variant = "";
                    }
                }
                else
                {
                    $productVariant = craft()->commerce_variants->getVariantById($productVariant->defaultVariantId);
                    $category = $productVariant->getProduct()->getType()['name'];
                    $name = $productVariant->title;
                    $variant = "";
                }
            }

            $productData = [
                'sku' => $productVariant->sku,
                'name' => $name,
                'price' => number_format($productVariant->price, 2, '.', ''),
                'category' => $category,
/*
                'brand' => "",
                'list' => "",
                'position' => "",
*/
            ];

            if ($variant)
                $productData['variant'] = $variant;
            $settings = craft()->plugins->getPlugin('instantanalytics')->getSettings();
            if (isset($settings) && isset($settings['productCategoryField']) && $settings['productCategoryField'] != "")
                $productData['category'] = $this->_pullDataFromField($productVariant, $settings['productCategoryField']);
            if (isset($settings) && isset($settings['productBrandField']) && $settings['productBrandField'] != "")
                $productData['brand'] = $this->_pullDataFromField($productVariant, $settings['productBrandField']);

            $result = $productData;
        }
        return $result;
    } /* -- getProductData */

    /**
     * Add a product impression from a Craft Commerce Product or Variant
     * @param IAnalytics $analytics the Analytics object
     * @param Commerce_ProductModel or Commerce_VariantModel  $productVariant the Product or Variant
     * @param int  $index Where the product appears in the list
     */
    public function addCommerceProductImpression($analytics = null, $productVariant = null, $index = 0)
    {
        if ($productVariant)
        {
            if ($analytics)
            {
                $productData = $this->getProductDataFromProduct($productVariant);

                //Add the product to the hit to be sent
                $analytics->addProductImpression($productData, $index);
                InstantAnalyticsPlugin::log("addCommerceProductDetailView for `" . $productData['sku'] . "` - `" . $productData['name'] . "` - `" . $productData['name'] . "`", LogLevel::Info, false);
            }
        }
    } /* -- addCommerceProductImpression */

    /**
     * Add a product detail view from a Craft Commerce Product or Variant
     * @param IAnalytics $analytics the Analytics object
     * @param Commerce_ProductModel or Commerce_VariantModel  $productVariant the Product or Variant
     */
    public function addCommerceProductDetailView($analytics = null, $productVariant = null)
    {
        if ($productVariant)
        {
            if ($analytics)
            {
                $productData = $this->getProductDataFromProduct($productVariant);

                // Don't forget to set the product action, in this case to DETAIL
                $analytics->setProductActionToDetail();

                //Add the product to the hit to be sent
                $analytics->addProduct($productData);
                InstantAnalyticsPlugin::log("addCommerceProductDetailView for `" . $productData['sku'] . "` - `" . $productData['name'] . "` - `" . $productData['name'] . "`", LogLevel::Info, false);
            }
        }
    } /* -- addCommerceProductDetailView */

    /**
     * Add a checkout step and option to an Analytics object
     * @param IAnalytics $analytics the Analytics object
     * @param Commerce_OrderModel  $orderModel the Product or Variant
     * @param int $step the checkout step
     * @param string $option the checkout option
     */
    public function addCommerceCheckoutStep($analytics = null, $orderModel = null, $step = 1, $option = "")
    {
        if ($orderModel)
        {
            if ($analytics)
            {
                // Add each line item in the transaction
                // Two cases - variant and non variant products
                foreach ($orderModel->lineItems as $key => $lineItem)
                    $this->addProductDataFromLineItem($analytics, $lineItem);
                $analytics->setCheckoutStep($step);
                if ($option)
                    $analytics->setCheckoutStepOption($option);

                // Don't forget to set the product action, in this case to CHECKOUT
                $analytics->setProductActionToCheckout();
                InstantAnalyticsPlugin::log("addCommerceCheckoutStep step: `" . $step . "` with option: `" . $option . "`", LogLevel::Info, false);
           }
        }
    } /* -- addCommerceCheckoutStep */

    /**
     * Add a Craft Commerce LineItem to an Analytics object
     * @return string the title of the product
     */
    public function addProductDataFromLineItem($analytics = null, $lineItem = null)
    {
        $result = "";
        if ($lineItem)
        {
            if ($analytics)
            {
                //This is the same for both variant and non variant products
                $productData = [
                    'sku' => $lineItem->purchasable->sku,
                    'price' => $lineItem->salePrice,
                    'quantity' => $lineItem->qty,
                ];

                if (!$lineItem->purchasable->product->type->hasVariants)
                {
                //No variants (i.e. default variant)
                    $productData['name'] = $lineItem->purchasable->title;
                    $productData['category'] = $lineItem->purchasable->product->type['name'];
                }
                else
                {
                // Product with variants
                    $productData['name'] = $lineItem->purchasable->product->title;
                    $productData['category'] = $lineItem->purchasable->product->type['name'];
                    $productData['variant'] = $lineItem->purchasable->title;
                }

                $result = $productData['name'];

                $settings = craft()->plugins->getPlugin('instantanalytics')->getSettings();
                if (isset($settings) && isset($settings['productCategoryField']) && $settings['productCategoryField'] != "")
                    $productData['category'] = $this->_pullDataFromField($lineItem->purchasable->product, $settings['productCategoryField']);
                if (isset($settings) && isset($settings['productBrandField']) && $settings['productBrandField'] != "")
                    $productData['brand'] = $this->_pullDataFromField($lineItem->purchasable->product, $settings['productBrandField']);
                //Add each product to the hit to be sent
                $analytics->addProduct($productData);
            }
        }
        return $result;
    } /* -- addProductDataFromLineItem */

    /**
     * Add a Craft Commerce OrderModel to an Analytics object
     * @param IAnalytics $analytics the Analytics object
     * @param Commerce_OrderModel  $orderModel the Product or Variant
     */
    public function addCommerceOrderToAnalytics($analytics = null, $orderModel = null)
    {
        if ($orderModel)
        {
            if ($analytics)
            {
                // First, include the transaction data
                $analytics->setTransactionId($orderModel->number)
                    ->setRevenue($orderModel->totalPrice)
                    ->setTax($orderModel->TotalTax)
                    ->setShipping($orderModel->totalShippingCost);

                // Coupon code?
                if ($orderModel->couponCode)
                    $analytics->setCouponCode($orderModel->couponCode);

                // Add each line item in the transaction
                // Two cases - variant and non variant products
                foreach ($orderModel->lineItems as $key => $lineItem)
                    $this->addProductDataFromLineItem($analytics, $lineItem);
            }
        }
    } /* -- addCommerceOrderToAnalytics */

    /**
     * Send analytics information for the completed order
     * @param IAnalytics $analytics the Analytics object
     * @param Commerce_OrderModel  $orderModel the Product or Variant
     */
    public function orderComplete($orderModel = null)
    {
        if ($orderModel)
        {
            $analytics = $this->eventAnalytics("Commerce", "Purchase", $orderModel->number, $orderModel->totalPrice);
            if ($analytics)
            {
                $this->addCommerceOrderToAnalytics($analytics, $orderModel);
                // Don't forget to set the product action, in this case to PURCHASE
                $analytics->setProductActionToPurchase();

                $analytics->sendEvent();

                InstantAnalyticsPlugin::log("orderComplete for `Commerce` - `Purchase` - `" . $orderModel->number . "` - `" . $orderModel->totalPrice . "`", LogLevel::Info, false);
            }
        }
    } /* -- orderComplete */

    /**
     * Send analytics information for the item added to the cart
     * @param Commerce_OrderModel  $orderModel the Product or Variant
     * @param Commerce_LineItemModel  $lineItem the line item that was added
     */
    public function addToCart($orderModel = null, $lineItem = null)
    {
        if ($lineItem)
        {
            $title = $lineItem->purchasable->title;
            $quantity = $lineItem->qty;
            $analytics = $this->eventAnalytics("Commerce", "Add to Cart", $title, $quantity);
            if ($analytics)
            {
                $title = $this->addProductDataFromLineItem($analytics, $lineItem);
                $analytics->setEventLabel($title);
                // Don't forget to set the product action, in this case to ADD
                $analytics->setProductActionToAdd();
                $analytics->sendEvent();

                InstantAnalyticsPlugin::log("addToCart for `Commerce` - `Add to Cart` - `" . $title . "` - `" . $quantity . "`", LogLevel::Info, false);
            }
        }
    } /* -- addToCart */

    /**
     * Send analytics information for the item removed from the cart
     */
    public function removeFromCart($orderModel = null, $lineItem = null)
    {
        if ($lineItem)
        {
            $title = $lineItem->purchasable->title;
            $quantity = $lineItem->qty;
            $analytics = $this->eventAnalytics("Commerce", "Remove from Cart", $title, $quantity);
            if ($analytics)
            {
                $title = $this->addProductDataFromLineItem($analytics, $lineItem);
                $analytics->setEventLabel($title);
                // Don't forget to set the product action, in this case to ADD
                $analytics->setProductActionToRemove();
                $analytics->sendEvent();

                InstantAnalyticsPlugin::log("removeFromCart for `Commerce` - `Remove from Cart` - `" . $title . "` - `" . $quantity . "`", LogLevel::Info, false);
            }
        }
    } /* -- removeFromCart */

    /**
     * _shouldSendAnalytics determines whether we should be sending Google Analytics data
     * @return bool
     */
    public function shouldSendAnalytics()
    {
        $result = true;

        if (!craft()->config->get("sendAnalyticsData", "instantanalytics"))
            return false;
        if (!craft()->config->get("sendAnalyticsInDevMode", "instantanalytics") && craft()->config->get('devMode'))
            return false;
        if (craft()->isConsole())
            return false;
        if (craft()->request->isCpRequest())
            return false;
        if (craft()->request->isLivePreview())
            return false;

/* -- Check the $_SERVER[] super-global exclusions */

        $exclusions = craft()->config->get("serverExcludes", "instantanalytics");
        if (isset($exclusions) && is_array($exclusions))
        {
            foreach ($exclusions as $match => $matchArray)
            {
                if (isset($_SERVER[$match]))
                {
                    foreach ($matchArray as $matchItem)
                    {
                        if (preg_match($matchItem, $_SERVER[$match]))
                            return false;
                    }
                }
            }
        }

/* -- Filter out bot/spam requests via UserAgent */

        if (craft()->config->get("filterBotUserAgents", "instantanalytics"))
        {

            $CrawlerDetect = new CrawlerDetect;

// Check the user agent of the current 'visitor'
            if ($CrawlerDetect->isCrawler())
                return false;
        }

/* -- Filter by user group */

        $session = craft()->userSession;
        if ($session)
        {
            $user = $session->getUser();
            $exclusions = craft()->config->get("groupExcludes", "instantanalytics");

            if (craft()->config->get("adminExclude", "instantanalytics") && $session->isAdmin())
                return false;

            if ($user && isset($exclusions) && is_array($exclusions))
            {
                if ($session->isLoggedIn())
                {
                    foreach ($exclusions as $matchItem)
                    {
                        if ($user->isInGroup($matchItem))
                            return false;
                    }
                }
            }
        }

        return $result;
    } /* -- _shouldSendAnalytics */

    /**
     * Extract the value of a field
     * @param Commerce_OrderModel  $orderModel the Product or Variant
     * @param Commerce_LineItemModel  $lineItem the line item that was added
     * @return string
     */
    private function _pullDataFromField($productVariant, $fieldHandle)
    {
        $result = "";
        if ($productVariant)
        {
            if ($fieldHandle)
            {
                $srcField = $productVariant[$fieldHandle];
                if ($srcField == null)
                    $srcField = $productVariant->product->content->attributes[$fieldHandle];

                if (isset($srcField->elementType))
                {
                    switch ($srcField->elementType->classHandle)
                    {
                        case "Neo":
                            break;

                        case ElementType::MatrixBlock:
                            break;

                        case ElementType::Tag:
                            break;

                        default:
                            $result = strip_tags($srcField);
                            break;
                    }
                }
                else
                {
                    $result = strip_tags($srcField);
                }
            }
        }
        return $result;
    } /* -- _pullDataFromField */

    /**
     * Get the Google Analytics object, primed with the default values
     * @return Analytics object
     */
    private function _getAnalyticsObj()
    {
        $analytics = null;
        $settings = craft()->plugins->getPlugin('instantanalytics')->getSettings();
        if (isset($settings) && isset($settings['googleAnalyticsTracking']) && $settings['googleAnalyticsTracking'] != "")
        {
            $analytics = new IAnalytics();
            if ($analytics)
            {
                $userAgent = "User-Agent:Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13\r\n";
                if (isset($_SERVER['HTTP_USER_AGENT']))
                    $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $referrer = "";
                if (isset($_SERVER['HTTP_REFERER']))
                    $referrer = $_SERVER['HTTP_REFERER'];
                $analytics->setProtocolVersion('1')
                    ->setTrackingId($settings['googleAnalyticsTracking'])
                    ->setIpOverride($_SERVER['REMOTE_ADDR'])
                    ->setUserAgentOverride($userAgent)
                    ->setDocumentReferrer($referrer)
                    ->setAsyncRequest(false)
                    ->setClientId($this->_gaParseCookie());

                $gclid = $this->_getGclid();
                if ($gclid)
                    $analytics->setGoogleAdwordsId($gclid);

/* -- If SEOmatic is installed, set the affiliation as well */

                $seomatic = craft()->plugins->getPlugin('Seomatic');
                if ($seomatic && $seomatic->isInstalled && $seomatic->isEnabled)
                {
                    $seomaticSettings = craft()->seomatic->getSettings(craft()->language);
                    $analytics->setAffiliation($seomaticSettings['siteSeoName']);
                }

            }
        }

        return $analytics;
    } /* -- _getAnalyticsObj */

    /**
     * _getGclid get the `gclid` and sets the 'gclid' cookie
     */
    private function _getGclid()
    {
        $gclid = "";
        if (isset($_GET['gclid']))
        {
            $gclid = $_GET['gclid'];
            if (!empty($gclid))
            {
                setcookie("gclid", $gclid, time() + (10 * 365 * 24 * 60 * 60));
            }
        }
        return $gclid;
    } /* -- _getGclid */

    /**
     * _gaParseCookie handles the parsing of the _ga cookie or setting it to a unique identifier
     * @return string the cid
     */
    private function _gaParseCookie()
    {
        if (isset($_COOKIE['_ga']))
        {
            list($version, $domainDepth, $cid1, $cid2) = preg_split('[\.]', $_COOKIE["_ga"], 4);
            $contents = array('version' => $version, 'domainDepth' => $domainDepth, 'cid' => $cid1 . '.' . $cid2);
            $cid = $contents['cid'];
        }
        else
        {
            if (isset($_COOKIE['_ia']) && $_COOKIE['_ia'] !='' )
                $cid = $_COOKIE['_ia'];
            else
                $cid = $this->_gaGenUUID();
        }
        setcookie('_ia', $cid, time()+60*60*24*730); // Two years
        return $cid;
    } /* -- _gaParseCookie */

    /**
     * _gaGenUUID Generate UUID v4 function - needed to generate a CID when one isn't available
     * @return string The generated UUID
     */
    private function _gaGenUUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                // 32 bits for "time_low"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                // 16 bits for "time_mid"
                mt_rand(0, 0xffff),
                // 16 bits for "time_hi_and_version",
                // four most significant bits holds version number 4
                mt_rand(0, 0x0fff) | 0x4000,
                // 16 bits, 8 bits for "clk_seq_hi_res",
                // 8 bits for "clk_seq_low",
                // two most significant bits holds zero and one for variant DCE1.1
                mt_rand(0, 0x3fff) | 0x8000,
                // 48 bits for "node"
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    } /* -- _gaGenUUID */

}