<?php
/**
 * Copyright (c) Enalean, 2015 - 2017. All Rights Reserved.
 * Copyright (c) STMicroelectronics, 2008. All Rights Reserved.
 *
 * Originally written by Manuel VACELET, 2008
 *
 * This file is a part of Tuleap.
 *
 * Tuleap is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * Tuleap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Tuleap; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

use Tuleap\BurningParrotCompatiblePageEvent;
use Tuleap\Dashboard\Project\ProjectDashboardController;
use Tuleap\Project\Admin\ProjectDetailsPresenter;
use Tuleap\SVN\DiskUsage\Collector as SVNCollector;
use Tuleap\SVN\DiskUsage\Retriever as SVNRetriever;
use Tuleap\CVS\DiskUsage\Retriever as CVSRetriever;
use Tuleap\CVS\DiskUsage\Collector as CVSCollector;
use Tuleap\CVS\DiskUsage\FullHistoryDao;

require_once 'autoload.php';
require_once 'constants.php';

class StatisticsPlugin extends Plugin {

    function __construct($id) {
        parent::__construct($id);
        $this->addHook('cssfile',                  'cssFile',                false);
        $this->addHook('site_admin_option_hook',   'site_admin_option_hook', false);
        $this->addHook('root_daily_start',         'root_daily_start',       false);
        $this->addHook('widget_instance',          'widget_instance',        false);
        $this->addHook('widgets',                  'widgets',                false);
        $this->addHook('admin_toolbar_data',       'admin_toolbar_data',     false);
        $this->addHook('usergroup_data',           'usergroup_data',         false);
        $this->addHook('groupedit_data',           'groupedit_data',         false);
        $this->addHook(Event::WSDL_DOC2SOAP_TYPES, 'wsdl_doc2soap_types',    false);

        $this->addHook(Event::GET_SYSTEM_EVENT_CLASS);
        $this->addHook(Event::SYSTEM_EVENT_GET_CUSTOM_QUEUES);
        $this->addHook(Event::SYSTEM_EVENT_GET_TYPES_FOR_CUSTOM_QUEUE);
        $this->addHook(Event::AFTER_MASSMAIL_TO_PROJECT_ADMINS);

        $this->addHook(BurningParrotCompatiblePageEvent::NAME);
        $this->addHook(ProjectDetailsPresenter::GET_MORE_INFO_LINKS);

        $this->addHook('aggregate_statistics');
        $this->addHook('get_statistics_aggregation');

        $this->addHook(Event::BURNING_PARROT_GET_STYLESHEETS);
        $this->addHook(Event::BURNING_PARROT_GET_JAVASCRIPT_FILES);
    }

    /** @see Event::GET_SYSTEM_EVENT_CLASS */
    public function get_system_event_class($params) {
        switch($params['type']) {
            case SystemEvent_STATISTICS_DAILY::NAME:
                $queue = new SystemEventQueueStatistics();
                $params['class'] = 'SystemEvent_STATISTICS_DAILY';
                $params['dependencies'] = array(
                    $queue->getLogger(),
                    $this->getConfigurationManager(),
                    $this->getDiskUsagePurger($queue->getLogger()),
                    $this->getDiskUsageManager()
                );
                break;
            default:
                break;
        }
    }

    /** @see Event::SYSTEM_EVENT_GET_CUSTOM_QUEUES */
    public function system_event_get_custom_queues(array $params) {
        $params['queues'][SystemEventQueueStatistics::NAME] = new SystemEventQueueStatistics();
    }

    /** @see Event::SYSTEM_EVENT_GET_TYPES_FOR_CUSTOM_QUEUE */
    public function system_event_get_types_for_custom_queue($params) {
        if ($params['queue'] === SystemEventQueueStatistics::NAME) {
            $params['types'][] = SystemEvent_STATISTICS_DAILY::NAME;
        }
    }

    /** @see Event::AFTER_MASSMAIL_TO_PROJECT_ADMINS */
    public function after_massmail_to_project_admins($params)
    {
        $request = HTTPRequest::instance();
        if ($request->get('project_over_quota')) {
            $GLOBALS['Response']->redirect("/plugins/statistics/project_over_quota.php");
        }
    }

    function getPluginInfo() {
        if (!$this->pluginInfo instanceof StatisticsPluginInfo) {
            include_once('StatisticsPluginInfo.class.php');
            $this->pluginInfo = new StatisticsPluginInfo($this);
        }
        return $this->pluginInfo;
    }

    function site_admin_option_hook($params)
    {
        $params['plugins'][] = array(
            'label' => 'Statistics',
            'href'  => $this->getPluginPath() . '/'
        );
    }

    public function burningParrotCompatiblePage(BurningParrotCompatiblePageEvent $event)
    {
        if (strpos($_SERVER['REQUEST_URI'], $this->getPluginPath()) === 0
            && ! strpos($_SERVER['REQUEST_URI'], 'project_stat.php')
        ) {
            $event->setIsInBurningParrotCompatiblePage();
        }
    }

    private function getConfigurationManager() {
        return new Statistics_ConfigurationManager(
            new Statistics_ConfigurationDao()
        );
    }

    private function getDiskUsagePurger(Logger $logger) {
        return new Statistics_DiskUsagePurger(
            new Statistics_DiskUsageDao(),
            $logger
        );
    }

    /**
     * @see root_daily_start
     */
    public function root_daily_start($params) {
        SystemEventManager::instance()->createEvent(
            SystemEvent_STATISTICS_DAILY::NAME,
            null,
            SystemEvent::PRIORITY_LOW,
            SystemEvent::OWNER_ROOT
        );
    }

    /**
     * Hook.
     *
     * @param $params
     *
     * @return void
     */
    function admin_toolbar_data($params) {
        $groupId = $params['group_id'];
        if ($groupId) {
            echo ' | <A HREF="'.$this->getPluginPath().'/project_stat.php?group_id='.$groupId.'">'.$GLOBALS['Language']->getText('plugin_statistics_admin_page', 'show_statistics').'</A>';
        }
    }

    /**
     * Display link to user disk usage for site admin
     *
     * @param $params
     *
     * @return void
     */
    function usergroup_data($params)
    {
        $user_url_params = array(
            'menu' => 'one_user_details',
            'user' => $params['user']->getRealName().' ('.$params['user']->getUserName() .')'
        );

        $params['links'][] = array(
            'href'  => $this->getPluginPath() . '/disk_usage.php?'.http_build_query($user_url_params),
            'label' => $GLOBALS['Language']->getText('plugin_statistics_admin_page', 'show_statistics')
        );
    }

    /** @see ProjectDetailsPresenter::GET_MORE_INFO_LINKS */
    function get_more_info_links($params) {
        if (! UserManager::instance()->getCurrentUser()->isSuperUser()) {
            return;
        }

        $project_url_params = array(
            'menu'           => 'services',
            'project_filter' => $params['project']->getUnconvertedPublicName().' ('.$params['project']->getUnixName() .')'
        );
        $params['links'][] = array(
            'href'  => $this->getPluginPath().'/disk_usage.php?'.http_build_query($project_url_params),
            'label' => $GLOBALS['Language']->getText('plugin_statistics_admin_page', 'show_statistics')
        );
    }

    /**
     * Instanciate the widget
     *
     * @param Array $params params of the event
     *
     * @return void
     */
    function widget_instance($params) {
        if ($params['widget'] == 'plugin_statistics_projectstatistics') {
            include_once 'Statistics_Widget_ProjectStatistics.class.php';
            $params['instance'] = new Statistics_Widget_ProjectStatistics();
        }
    }

    /**
     * Add the widget to the list
     *
     * @param Array $params params of the event
     *
     * @return void
     */
    function widgets($params) {
        if ($params['owner_type'] == ProjectDashboardController::LEGACY_DASHBOARD_TYPE) {
            $params['codendi_widgets'][] = 'plugin_statistics_projectstatistics';
        }
    }

    public function uninstall()
    {
        $this->removeOrphanWidgets(array('plugin_statistics_projectstatistics'));
    }

    function cssFile($params) {
        // This stops styles inadvertently clashing with the main site.
        if (strpos($_SERVER['REQUEST_URI'], $this->getPluginPath()) === 0 ||
            strpos($_SERVER['REQUEST_URI'], '/widgets/') === 0
        ) {
            echo '<link rel="stylesheet" type="text/css" href="'.$this->getThemePath().'/css/style.css" />'."\n";
        }
    }

    public function processSOAP(Codendi_Request $request) {
        $uri           = $this->getSoapUri();
        $service_class = 'Statistics_SOAPServer';
        require_once $service_class .'.class.php';

        if ($request->exist('wsdl')) {
            $this->dumpWSDL($uri, $service_class);
        } else {
            $this->instantiateSOAPServer($uri, $service_class);
        }
    }

    private function dumpWSDL($uri, $service_class) {
        require_once 'common/soap/SOAP_NusoapWSDL.class.php';
        $wsdlGen = new SOAP_NusoapWSDL($service_class, 'TuleapStatisticsAPI', $uri);
        $wsdlGen->dumpWSDL();
    }

    private function instantiateSOAPServer($uri, $service_class) {
        require_once 'common/soap/SOAP_RequestValidator.class.php';
        require_once 'Statistics_DiskUsageManager.class.php';
        $user_manager           = UserManager::instance();
        $project_manager        = ProjectManager::instance();
        $soap_request_validator = new SOAP_RequestValidator($project_manager, $user_manager);
        $disk_usage_manager     = $this->getDiskUsageManager();
        $project_quota_manager  = new ProjectQuotaManager();

        $server = new TuleapSOAPServer($uri.'/?wsdl', array('cache_wsdl' => WSDL_CACHE_NONE));
        $server->setClass($service_class, $soap_request_validator, $disk_usage_manager, $project_quota_manager);
        $server->handle();
    }

    /**
     * @return Statistics_DiskUsageManager
     */
    private function getDiskUsageManager()
    {
        $disk_usage_dao  = new Statistics_DiskUsageDao();
        $svn_log_dao     = new SVN_LogDao();
        $svn_retriever   = new SVNRetriever($disk_usage_dao);
        $svn_collector   = new SVNCollector($svn_log_dao, $svn_retriever);
        $cvs_history_dao = new FullHistoryDao();
        $cvs_retriever   = new CVSRetriever($disk_usage_dao);
        $cvs_collector   = new CVSCollector($cvs_history_dao, $cvs_retriever);

        return new Statistics_DiskUsageManager(
            $disk_usage_dao,
            $svn_collector,
            $cvs_collector,
            EventManager::instance()
        );
    }

    private function getSoapUri() {
        return HTTPRequest::instance()->getServerUrl().'/plugins/statistics/soap';
    }

    public function renderWSDL() {
        require_once 'common/soap/SOAP_WSDLRenderer.class.php';
        $uri = $this->getSoapUri();
        $wsdl_renderer = new SOAP_WSDLRenderer();
        $wsdl_renderer->render($uri .'/?wsdl');
    }

    public function wsdl_doc2soap_types($params) {
        $params['doc2soap_types'] = array_merge($params['doc2soap_types'], array(
            'arrayofstatistics' => 'tns:ArrayOfStatistics',
        ));
    }

    public function aggregate_statistics($params) {
        $statistics_aggregator = new StatisticsAggregatorDao();
        $statistics_aggregator->addStatistic($params['project_id'], $params['statistic_name']);
    }

    public function get_statistics_aggregation($params) {
        $statistics_aggregator = new StatisticsAggregatorDao();
        $params['result'] = $statistics_aggregator->getStatistics(
            $params['statistic_name'],
            $params['date_start'],
            $params['date_end']
        );
    }

    public function burning_parrot_get_stylesheets(array $params)
    {
        if (strpos($_SERVER['REQUEST_URI'], $this->getPluginPath()) === 0) {
            $variant = $params['variant'];
            $params['stylesheets'][] = $this->getThemePath() .'/css/style-'. $variant->getName() .'.css';
        }
    }

    public function burning_parrot_get_javascript_files(array $params)
    {
        if (strpos($_SERVER['REQUEST_URI'], $this->getPluginPath()) === 0) {
            $params['javascript_files'][] = $this->getPluginPath() . '/js/admin.js';
        }
    }
}
