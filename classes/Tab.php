<?php
/**
 * Class Tab
 * by: Lee
 * Date: 4/5/2021
 *
 * A single tab object that can be a part of the Tabbar2 Collection Class
 */

class Tab
{
    private string $name = ''; // the display name for the tab
    private string $tabId = '';
    private string $function = ''; // a callable function to fill the tab body
    private string $elementID = ''; // an pre-filled element within the tab body that will be hidden or shown when tab is selected
    private bool $visible = true;
    private array $params = array(); // serialized variables to be passed to the function
    private string $width = ''; // width of the tab


    /**
     * Tabs constructor.
     *
     * @param string $name
     * @param string $tabId
     * @param string|null $function
     * @param string|null $elementID
     * @param string|null $width
     * @param array|null $params
     */
    public function __construct(string $name, string $tabId, ?string $function = '', ?string $elementID = '', ?string $width = '', ?array $params = array())
    {
        $this->name = $name;
        $this->tabId = $tabId;
        $this->function = $function;
        $this->elementID = $elementID;
        $this->width = $width;
        $this->params = $params;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getTabId(): string
    {
        return $this->tabId;
    }

    /**
     * @return string
     */
    public function getFunction(): string
    {
        return $this->function;
    }

    /**
     * @return string
     */
    public function getElementID(): string
    {
        return $this->elementID;
    }

    /**
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return array
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * @return string
     */
    public function getWidth(): string
    {
        return $this->width;
    }

}
