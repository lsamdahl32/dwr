<?php
/**
 * Tabbar2 class
 * Shows a tab bar of up to 9 tabs, current tab stored in session
 * Lee updated all escaping to use gls_esc_xxx functions 8/19/2020
 *
 * Created by PhpStorm.
 * User: Lee Samdahl
 * Date: 10/31/2018
 * Time: 1:36 PM
 *
 * Modified for multiple tab bars on one page and to support pre-loading of tab content (i.e. no AJAX)
 * Javascript moved to separate module tabbar2.js and tabbar2.min.js
 * Date: 4/5/2021
 *
 */
// include the Tab class
require_once(PLMPATH . 'classes/Tab.php');

class Tabbar2
{
    private int $tabCount = 0;
    private array $tabs = array();
    private string $identifier = '';
    private int $currentTab = 0;
    private bool $useAjax = false; // if true, use AJAX calls to get the content
    private string $refreshCallback = ''; // a js function that will be called every tab click
    private string $tabBodyId = '';
    public string $ajaxHandler = '';

    /**
     * Tabbar2 constructor.
     *
     * @param string $identifier - a short key that is unique to each tabbar to allow multiple tab bars
     * @param bool $useAjax - if true use AJAX to load tab content - if false, entire page will refresh upon tab click
     * @param string $tabBodyId - id of content element for the tab. If not = 'tab_body' then expects the calling program to provide a <div> with the unique id.
     */
    public function __construct(string $identifier, bool $useAjax = false, string $tabBodyId = 'tab_body')
    {
        $this->identifier = $identifier;
        $this->useAjax = $useAjax;
        $this->tabBodyId = $tabBodyId;
        $this->ajaxHandler = $_SERVER['REQUEST_URI'];
    }

    /**
     * @return array
     */
    public function getTabs():array
    {
        return $this->tabs;
    }

    /**
     * @param Tab $tab
     */
    public function addTab(Tab $tab)
    {
        if ($this->tabCount < 9) {
            $this->tabs[] = $tab;
            $this->tabCount = count($this->tabs);
        }
    }

    /**
     * Only works before the tabs have been shown. After that use the Javascript setTab function
     * @param $currentTab
     */
    public function setCurrentTab($currentTab)
    {
        if (is_numeric($currentTab)) {
            $this->currentTab = $currentTab;
            $_SESSION[$this->identifier.'_tab_selected'] = $this->tabs[$currentTab]->getTabId();
        } else {
            for ($i = 0; $i < count($this->tabs); $i++) {
                if ($this->tabs[$i]->getTabId() == $currentTab) {
                    $this->currentTab = $i;
                    break;
                }
            }
            $_SESSION[$this->identifier.'_tab_selected'] = $currentTab;
        }
    }

    /**
     * @return mixed
     */
    public function getCurrentTab()
    {
        return $this->tabs[$this->currentTab]->getTabId();
    }

    /**
     * Remove all tabs from this instance
     */
    public function clearTabs()
    {
        $this->tabs = array();
        $this->tabCount = 0;
        $this->currentTab = 0;
    }

    /**
     * Output the HTML and JS settings for the tabs. Should be called at the place where tabs should be shown.
     */
    public function showTabs()
    {
        if (isset($_SESSION[$this->identifier.'_tab_selected'])) {
            // session overrides default set in addTab
            $this->setCurrentTab($_SESSION[$this->identifier.'_tab_selected'], false);
        }
        $widthOfTabs = $this->tabCount * 180;
        ?>
        <div class="tab_bar_container" id="tab_bar_container<?=$this->identifier?>">
            <?php
            $this->refreshTabs();
            ?>
        </div>
        <?php if ($this->tabBodyId == 'tab_body') { // include the default tab body if none specified ?>
        <div id="tab_body<?=$this->identifier?>" class="tab_body">
            <?php
            if (is_callable($this->tabs[$this->currentTab]->getFunction()) and ($this->tabCount == 1 or $this->useAjax == false)) { // only load if single tab with function
                call_user_func($this->tabs[$this->currentTab]->getFunction(), $this->tabs[$this->currentTab]->getParams()[0], $this->tabs[$this->currentTab]->getParams()[1]);
            }
            ?>
        </div>
        <?php
        }
        ?>
        <script>

            var tabbar<?=$this->identifier?> = new tabbar('<?=$this->identifier?>', {
                identifier:     "<?=gls_esc_js($this->identifier)?>",
                currentTab:     <?=gls_esc_js($this->currentTab)?>,
                currentTabID:   "<?=gls_esc_js($this->getCurrentTab())?>",
                tabBodyId:      "<?=gls_esc_js(($this->tabBodyId == 'tab_body')?$this->tabBodyId . $this->identifier:$this->tabBodyId) ?>",
                useAjax:        <?=($this->useAjax)? 'true' : 'false'?>,
                ajaxHandler:    "<?=gls_esc_js($this->ajaxHandler)?>",
                refreshCallback:"<?=gls_esc_js($this->refreshCallback)?>",
                linkField:      "<?=gls_esc_js($_POST['linkField'])?>",
                linkID:         "<?=gls_esc_js($_POST['linkID'])?>",
                disabled:       false,
                post:           <?=json_encode($_POST) // this could be a security risk if used on a public site ?>
            });

        </script>
        <?php
    }

    public function refreshTabs()
    {
        ?>
        <button class="tab_bar_scroll tab_bar_scroll<?=$this->identifier?> buttonBar" type="button" data-direction="left" style="display: none;" title="Scroll tabs to right">
            <i class="bi-caret-left"></i>
        </button>
        <div class="tab_bar_buttons_scroll_container" style="overflow-x: hidden; flex-grow: 1;">
            <div class="tab_bar_buttons_scroll tab_bar_buttons_scroll<?=$this->identifier?>">
                <?php
                for ($i = 0; $i < $this->tabCount; $i++) {
                    ?>
                    <button id="<?=$this->tabs[$i]->getTabId()?>" class="tab_bar_buttons tab_bar_buttons<?=$this->identifier?> <?=($i == $this->currentTab)? 'tab_bar_buttons_active':''?>"
                            data-tabIdentifier="<?=$this->tabs[$i]->getTabId()?>"
                            data-tabIndex="<?=$i?>"
                            data-function="<?=$this->tabs[$i]->getFunction()?>"
                            data-elementId="<?=$this->tabs[$i]->getElementID()?>"
                            data-param0="<?=$this->tabs[$i]->getParams()[0]?>"
                            data-param1="<?=$this->tabs[$i]->getParams()[1]?>"
                            <?=($this->tabs[$i]->getWidth() != '')?' style="width: '.$this->tabs[$i]->getWidth().'"':''?>>
                        <h3><?=gls_esc_html($this->tabs[$i]->getName())?></h3>
                    </button>
                    <?php
                }
                ?>
            </div>
        </div>
        <button class="tab_bar_scroll tab_bar_scroll<?=$this->identifier?> buttonBar" type="button" data-direction="right" style="display: none;" title="Scroll tabs to left">
            <i class="bi-caret-right"></i>
        </button>
        <?php
    }

    /**
     * @param string $refreshCallback
     */
    public function setRefreshCallback(string $refreshCallback)
    {
        $this->refreshCallback = $refreshCallback;
    }

    /**
     * @return string
     */
    public function getRefreshCallback(): string
    {
        return $this->refreshCallback;
    }


}
