<?php

namespace Moyasar\Magento2\Admin;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Custom renderer Moyaser Button
 */
class ButtonElement extends Field
{
    /**
     * Path to Button template
     */
    const BUTTON_TEMPLATE = 'Moyasar_Magento2::system/config/button.phtml';

    /**
     * Set Template
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::BUTTON_TEMPLATE);
        }
        return $this;
    }

    /**
     * Unset Website Value/Default
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button
     *
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $originalData = $element->getOriginalData();
        $this->addData(
            [
                'button_label' => __($originalData['button_label']),
                'button_url' => $originalData['button_url'],
                'html_id' => $element->getHtmlId(),
            ]
        );
        return $this->_toHtml();
    }

}
