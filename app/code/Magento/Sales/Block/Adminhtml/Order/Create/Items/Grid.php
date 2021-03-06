<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Magento
 * @package     Magento_Sales
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace Magento\Sales\Block\Adminhtml\Order\Create\Items;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Sales\Model\Quote\Item;
use Magento\Session\SessionManagerInterface;

/**
 * Adminhtml sales order create items grid block
 *
 * @category   Magento
 * @package    Magento_Sales
 * @author      Magento Core Team <core@magentocommerce.com>
 */
class Grid extends \Magento\Sales\Block\Adminhtml\Order\Create\AbstractCreate
{
    /**
     * Flag to check can items be move to customer storage
     *
     * @var bool
     */
    protected $_moveToCustomerStorage = true;

    /**
     * Tax data
     *
     * @var \Magento\Tax\Helper\Data
     */
    protected $_taxData = null;

    /**
     * Wishlist factory
     *
     * @var \Magento\Wishlist\Model\WishlistFactory
     */
    protected $_wishlistFactory;

    /**
     * Gift message save
     *
     * @var \Magento\GiftMessage\Model\Save
     */
    protected $_giftMessageSave;

    /**
     * Tax config
     *
     * @var \Magento\Tax\Model\Config
     */
    protected $_taxConfig;

    /**
     * Message helper
     *
     * @var \Magento\GiftMessage\Helper\Message
     */
    protected $_messageHelper;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Model\Session\Quote $sessionQuote
     * @param \Magento\Sales\Model\AdminOrder\Create $orderCreate
     * @param \Magento\Wishlist\Model\WishlistFactory $wishlistFactory
     * @param \Magento\GiftMessage\Model\Save $giftMessageSave
     * @param \Magento\Tax\Model\Config $taxConfig
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\GiftMessage\Helper\Message $messageHelper
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Model\Session\Quote $sessionQuote,
        \Magento\Sales\Model\AdminOrder\Create $orderCreate,
        \Magento\Wishlist\Model\WishlistFactory $wishlistFactory,
        \Magento\GiftMessage\Model\Save $giftMessageSave,
        \Magento\Tax\Model\Config $taxConfig,
        \Magento\Tax\Helper\Data $taxData,
        \Magento\GiftMessage\Helper\Message $messageHelper,
        array $data = array()
    ) {
        $this->_messageHelper = $messageHelper;
        $this->_wishlistFactory = $wishlistFactory;
        $this->_giftMessageSave = $giftMessageSave;
        $this->_taxConfig = $taxConfig;
        $this->_taxData = $taxData;
        parent::__construct($context, $sessionQuote, $orderCreate, $data);
    }

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_search_grid');
    }

    /**
     * Get items
     *
     * @return Item[]
     */
    public function getItems()
    {
        $items = $this->getParentBlock()->getItems();
        $oldSuperMode = $this->getQuote()->getIsSuperMode();
        $this->getQuote()->setIsSuperMode(false);
        foreach ($items as $item) {
            // To dispatch inventory event sales_quote_item_qty_set_after, set item qty
            $item->setQty($item->getQty());
            $stockItem = $item->getProduct()->getStockItem();
            if ($stockItem instanceof \Magento\CatalogInventory\Model\Stock\Item) {
                // This check has been performed properly in Inventory observer, so it has no sense
                /*
                $check = $stockItem->checkQuoteItemQty($item->getQty(), $item->getQty(), $item->getQty());
                $item->setMessage($check->getMessage());
                $item->setHasError($check->getHasError());
                */
                if ($item->getProduct()->getStatus() == ProductStatus::STATUS_DISABLED) {
                    $item->setMessage(__('This product is disabled.'));
                    $item->setHasError(true);
                }
            }
        }
        $this->getQuote()->setIsSuperMode($oldSuperMode);
        return $items;
    }

    /**
     * Get session
     *
     * @return SessionManagerInterface
     */
    public function getSession()
    {
        return $this->getParentBlock()->getSession();
    }

    /**
     * Get item editable price
     *
     * @param Item $item
     * @return float
     */
    public function getItemEditablePrice($item)
    {
        return $item->getCalculationPrice() * 1;
    }

    /**
     * Get original editable price
     *
     * @param Item $item
     * @return float
     */
    public function getOriginalEditablePrice($item)
    {
        if ($item->hasOriginalCustomPrice()) {
            $result = $item->getOriginalCustomPrice() * 1;
        } elseif ($item->hasCustomPrice()) {
            $result = $item->getCustomPrice() * 1;
        } else {
            if ($this->_taxData->priceIncludesTax($this->getStore())) {
                $result = $item->getPriceInclTax() * 1;
            } else {
                $result = $item->getOriginalPrice() * 1;
            }
        }
        return $result;
    }

    /**
     * Get item original price
     *
     * @param Item $item
     * @return float
     */
    public function getItemOrigPrice($item)
    {
        //        return $this->convertPrice($item->getProduct()->getPrice());
        return $this->convertPrice($item->getPrice());
    }

    /**
     * Check gift messages availability
     *
     * @param Item|null $item
     * @return bool|null|string
     */
    public function isGiftMessagesAvailable($item = null)
    {
        if (is_null($item)) {
            return $this->_messageHelper->getIsMessagesAvailable('items', $this->getQuote(), $this->getStore());
        }

        return $this->_messageHelper->getIsMessagesAvailable('item', $item, $this->getStore());
    }

    /**
     * Check if allowed for gift message
     *
     * @param Item $item
     * @return bool
     */
    public function isAllowedForGiftMessage($item)
    {
        return $this->_giftMessageSave->getIsAllowedQuoteItem($item);
    }

    /**
     * Check if we need display grid totals include tax
     *
     * @return bool
     */
    public function displayTotalsIncludeTax()
    {
        $res = $this->_taxConfig->displayCartSubtotalInclTax(
            $this->getStore()
        ) || $this->_taxConfig->displayCartSubtotalBoth(
            $this->getStore()
        );
        return $res;
    }

    /**
     * Get subtotal
     *
     * @return false|float
     */
    public function getSubtotal()
    {
        $address = $this->getQuoteAddress();
        if ($this->displayTotalsIncludeTax()) {
            if ($address->getSubtotalInclTax()) {
                return $address->getSubtotalInclTax();
            }
            return $address->getSubtotal() + $address->getTaxAmount();
        } else {
            return $address->getSubtotal();
        }
        return false;
    }

    /**
     * Get subtotal with discount
     *
     * @return float
     */
    public function getSubtotalWithDiscount()
    {
        $address = $this->getQuoteAddress();
        if ($this->displayTotalsIncludeTax()) {
            return $address->getSubtotal() + $address->getTaxAmount() + $this->getDiscountAmount();
        } else {
            return $address->getSubtotal() + $this->getDiscountAmount();
        }
    }

    /**
     * Get discount amount
     *
     * @return float
     */
    public function getDiscountAmount()
    {
        return $this->getQuote()->getShippingAddress()->getDiscountAmount();
    }

    /**
     * Retrieve quote address
     *
     * @return \Magento\Sales\Model\Quote\Address
     */
    public function getQuoteAddress()
    {
        if ($this->getQuote()->isVirtual()) {
            return $this->getQuote()->getBillingAddress();
        } else {
            return $this->getQuote()->getShippingAddress();
        }
    }

    /**
     * Define if specified item has already applied custom price
     *
     * @param Item $item
     * @return bool
     */
    public function usedCustomPriceForItem($item)
    {
        return $item->hasCustomPrice();
    }

    /**
     * Define if custom price can be applied for specified item
     *
     * @param Item $item
     * @return bool
     */
    public function canApplyCustomPrice($item)
    {
        return !$item->isChildrenCalculated();
    }

    /**
     * Get qty title
     *
     * @param Item $item
     * @return string
     */
    public function getQtyTitle($item)
    {
        $prices = $item->getProduct()->getTierPrice();
        if ($prices) {
            $info = array();
            foreach ($prices as $data) {
                $qty = $data['price_qty'] * 1;
                $price = $this->convertPrice($data['price']);
                $info[] = __('Buy %1 for price %2', $qty, $price);
            }
            return implode(', ', $info);
        } else {
            return __('Item ordered qty');
        }
    }

    /**
     * Get tier price html
     *
     * @param Item $item
     * @return string
     */
    public function getTierHtml($item)
    {
        $html = '';
        $prices = $item->getProduct()->getTierPrice();
        if ($prices) {
            $info = $item->getProductType() ==
                \Magento\Catalog\Model\Product\Type::TYPE_BUNDLE ? $this->_getBundleTierPriceInfo(
                    $prices
                ) : $this->_getTierPriceInfo(
                    $prices
                );
            $html = implode('<br/>', $info);
        }
        return $html;
    }

    /**
     * Get tier price info to display in grid for Bundle product
     *
     * @param array $prices
     * @return string[]
     */
    protected function _getBundleTierPriceInfo($prices)
    {
        $info = array();
        foreach ($prices as $data) {
            $qty = $data['price_qty'] * 1;
            $info[] = __('%1 with %2 discount each', $qty, $data['price'] * 1 . '%');
        }
        return $info;
    }

    /**
     * Get tier price info to display in grid
     *
     * @param array $prices
     * @return string[]
     */
    protected function _getTierPriceInfo($prices)
    {
        $info = array();
        foreach ($prices as $data) {
            $qty = $data['price_qty'] * 1;
            $price = $this->convertPrice($data['price']);
            $info[] = __('%1 for %2', $qty, $price);
        }
        return $info;
    }

    /**
     * Get Custom Options of item
     *
     * @param Item $item
     * @return string
     */
    public function getCustomOptions(Item $item)
    {
        $optionStr = '';
        $this->_moveToCustomerStorage = true;
        if ($optionIds = $item->getOptionByCode('option_ids')) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                if ($option = $item->getProduct()->getOptionById($optionId)) {
                    $optionValue = $item->getOptionByCode('option_' . $option->getId())->getValue();

                    $optionStr .= $option->getTitle() . ':';

                    $quoteItemOption = $item->getOptionByCode('option_' . $option->getId());
                    $group = $option->groupFactory(
                        $option->getType()
                    )->setOption(
                        $option
                    )->setQuoteItemOption(
                        $quoteItemOption
                    );

                    $optionStr .= $group->getEditableOptionValue($quoteItemOption->getValue());
                    $optionStr .= "\n";
                }
            }
        }
        return $optionStr;
    }

    /**
     * Get flag for rights to move items to customer storage
     *
     * @return bool
     */
    public function getMoveToCustomerStorage()
    {
        return $this->_moveToCustomerStorage;
    }

    /**
     * Display subtotal including tax
     *
     * @param Item $item
     * @return string
     */
    public function displaySubtotalInclTax($item)
    {
        if ($item->getTaxBeforeDiscount()) {
            $tax = $item->getTaxBeforeDiscount();
        } else {
            $tax = $item->getTaxAmount() ? $item->getTaxAmount() : 0;
        }
        return $this->formatPrice($item->getRowTotal() + $tax);
    }

    /**
     * Display original price including tax
     *
     * @param Item $item
     * @return float
     */
    public function displayOriginalPriceInclTax($item)
    {
        $tax = 0;
        if ($item->getTaxPercent()) {
            $tax = $item->getPrice() * ($item->getTaxPercent() / 100);
        }
        return $this->convertPrice($item->getPrice() + $tax / $item->getQty());
    }

    /**
     * Display row total with discount including tax
     *
     * @param Item $item
     * @return string
     */
    public function displayRowTotalWithDiscountInclTax($item)
    {
        $tax = $item->getTaxAmount() ? $item->getTaxAmount() : 0;
        return $this->formatPrice($item->getRowTotal() - $item->getDiscountAmount() + $tax);
    }

    /**
     * Get including/excluding tax message
     *
     * @return string
     */
    public function getInclExclTaxMessage()
    {
        if ($this->_taxData->priceIncludesTax($this->getStore())) {
            return __('* - Enter custom price including tax');
        } else {
            return __('* - Enter custom price excluding tax');
        }
    }

    /**
     * Get store
     *
     * @return \Magento\Core\Model\Store
     */
    public function getStore()
    {
        return $this->getQuote()->getStore();
    }

    /**
     * Return html button which calls configure window
     *
     * @param Item $item
     * @return string
     */
    public function getConfigureButtonHtml($item)
    {
        $product = $item->getProduct();

        $options = array('label' => __('Configure'));
        if ($product->canConfigure()) {
            $options['onclick'] = sprintf('order.showQuoteItemConfiguration(%s)', $item->getId());
        } else {
            $options['class'] = ' disabled';
            $options['title'] = __('This product does not have any configurable options');
        }

        return $this->getLayout()->createBlock('Magento\Backend\Block\Widget\Button')->setData($options)->toHtml();
    }

    /**
     * Get order item extra info block
     *
     * @param Item $item
     * @return \Magento\View\Element\AbstractBlock
     */
    public function getItemExtraInfo($item)
    {
        return $this->getLayout()->getBlock('order_item_extra_info')->setItem($item);
    }

    /**
     * Returns whether moving to wishlist is allowed for this item
     *
     * @param Item $item
     * @return bool
     */
    public function isMoveToWishlistAllowed($item)
    {
        return $item->getProduct()->isVisibleInSiteVisibility();
    }

    /**
     * Retrieve collection of customer wishlists
     *
     * @return \Magento\Wishlist\Model\Resource\Wishlist\Collection
     */
    public function getCustomerWishlists()
    {
        return $this->_wishlistFactory->create()->getCollection()->filterByCustomerId($this->getCustomerId());
    }
}
