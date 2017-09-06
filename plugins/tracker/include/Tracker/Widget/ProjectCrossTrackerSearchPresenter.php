<?php
/**
 * Copyright (c) Enalean, 2017. All Rights Reserved.
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
 * along with Tuleap. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Tuleap\Tracker\Widget;

class ProjectCrossTrackerSearchPresenter
{
    /** @var  int */
    public $report_id;
    public $locale;
    public $date_format;
    public $too_many_trackers_selected_error;
    public $could_not_fetch_list_of_trackers_error;
    public $could_not_fetch_list_of_projects_error;
    public $put_report_error;
    public $put_report_success;
    public $fetch_report_error;
    public $fetch_query_error;
    public $project_label;
    public $tracker_label;
    public $add_button_label;
    public $please_choose_label;
    public $cancel;
    public $save_report;
    public $search;
    public $trackers_empty;
    public $artifact_label;
    public $summary_label;
    public $status_label;
    public $last_update_label;
    public $submitted_by_label;
    public $assigned_to_label;
    public $artifacts_empty;
    public $limited_results_label;

    public function __construct($report_id, \PFUser $user)
    {
        $this->report_id   = $report_id;
        $this->locale      = $user->getShortLocale();
        $this->date_format = $GLOBALS['Language']->getText('system', 'datefmt_short');

        $this->too_many_trackers_selected_error       = dgettext(
            'tuleap-tracker',
            'Tracker selection is limited to 10 trackers'
        );
        $this->could_not_fetch_list_of_trackers_error = dgettext(
            'tuleap-tracker',
            'Error while fetching the list of trackers of this project'
        );
        $this->could_not_fetch_list_of_projects_error = dgettext(
            'tuleap-tracker',
            'Error while fetching the list of projects you are member of'
        );
        $this->fetch_report_error                     = dgettext(
            'tuleap-tracker',
            'Error while fetching the cross tracker report'
        );
        $this->put_report_error                       = dgettext(
            'tuleap-tracker',
            'Error while updating the cross tracker report'
        );
        $this->put_report_success                     = dgettext(
            'tuleap-tracker',
            'Report has been successfully saved'
        );
        $this->fetch_query_error                      = dgettext(
            'tuleap-tracker',
            'Error while fetching the query result'
        );
        $this->project_label                          = dgettext('tuleap-tracker', 'Project');
        $this->tracker_label                          = dgettext('tuleap-tracker', 'Tracker');
        $this->add_button_label                       = dgettext('tuleap-tracker', 'Add');
        $this->please_choose_label                    = dgettext('tuleap-tracker', 'Please choose...');
        $this->cancel                                 = dgettext('tuleap-tracker', 'Cancel');
        $this->save_report                            = dgettext('tuleap-tracker', 'Save report');
        $this->search                                 = dgettext('tuleap-tracker', 'Search');
        $this->trackers_empty                         = dgettext('tuleap-tracker', 'No trackers selected');
        $this->artifact_label                         = dgettext('tuleap-tracker', 'Artifact');
        $this->status_label                           = dgettext('tuleap-tracker', 'Status');
        $this->last_update_label                      = dgettext('tuleap-tracker', 'Last update date');
        $this->submitted_by_label                     = dgettext('tuleap-tracker', 'Submitted by');
        $this->assigned_to_label                      = dgettext('tuleap-tracker', 'Assigned to');
        $this->artifacts_empty                        = dgettext('tuleap-tracker', 'No matching artifacts found');
        $this->limited_results_label                  = dgettext(
            'tuleap-tracker',
            'Only the first 30 results are shown'
        );
    }
}
