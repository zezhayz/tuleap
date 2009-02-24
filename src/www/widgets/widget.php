<?php

require_once('pre.php');
require_once('common/widget/Widget.class.php');
require_once('common/widget/WidgetLayoutManager.class.php');
require_once('common/widget/Valid_Widget.class.php');

$lm = new WidgetLayoutManager();

$request =& HTTPRequest::instance();
$good = false;
$redirect   = '/';
$vOwner = new Valid_Widget_Owner('owner');
$vOwner->required();
if ($request->valid($vOwner)) {
    $owner = $request->get('owner');
    $owner_id   = (int)substr($owner, 1);
    $owner_type = substr($owner, 0, 1);
    switch($owner_type) {
        case $lm->OWNER_TYPE_USER:
            $owner_id = user_getid();
            $redirect = '/my/';
            $good = true;
            break;
        case $lm->OWNER_TYPE_GROUP:
            if ($project = project_get_object($owner_id)) {
                $group_id = $owner_id;
                $_REQUEST['group_id'] = $_GET['group_id'] = $group_id;
                $request->params['group_id'] = $group_id; //bad!
                $redirect = '/projects/'. $project->getUnixName();
                $good = true;
            }
            break;
        default:
            break;
    }
    if ($good) {
        if ($request->exist('name')) {
            $param = $request->get('name');
            $name = array_pop(array_keys($param));
            $instance_id = (int)$param[$name];
            if ($widget =& Widget::getInstance($name)) {
                if ($widget->isAvailable()) {
                    switch ($request->get('action')) {
                        case 'rss':
                            $widget->displayRss();
                            exit();
                            break;
                        case 'update':
                            if ($layout_id = (int)$request->get('layout_id')) {
                                if ($owner_type == $lm->OWNER_TYPE_USER || user_ismember($group_id, 'A') || user_is_super_user()) {
                                    if ($request->get('cancel') || $widget->updatePreferences($request)) {
                                        $lm->hideWidgetPreferences($owner_id, $owner_type, $layout_id, $name, $instance_id);
                                    }
                                }
                            }
                            break;
                        case 'ajax':
                            if ($widget->isAjax()) {
                                $widget->loadContent($instance_id);
                                echo $widget->getContent();
                            }
                            break;
                        case 'iframe':
                            echo '<html><head>';
                            $GLOBALS['HTML']->displayStylesheetElements();
                            echo '</head><body class="main_body_row contenttable">';
                            $widget->loadContent($instance_id);
                            echo $widget->getContent();
                            echo '</body></html>';
                            exit;
                            break;
                        default:
                            break;
                    }
                }
            }
        }
    }
}
if (!$request->isAjax()) {
    $GLOBALS['Response']->redirect($redirect);
}
?>
