<?php
/**
 * Copyright (c) Enalean, 2014 - 2015. All Rights Reserved.
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

namespace Tuleap\Project\REST\v1;

use \ProjectManager;
use \UserManager;
use \ProjectUGroup;
use \PFUser;
use \UGroupManager;
use \URLVerification;
use \Tuleap\Project\REST\UserGroupRepresentation;
use \Tuleap\User\REST\UserRepresentation;
use \Tuleap\REST\Header;
use \Tuleap\REST\ProjectAuthorization;
use \Luracast\Restler\RestException;
use Tuleap\REST\AuthenticatedResource;

/**
 * Wrapper for user_groups related REST methods
 */
class UserGroupResource extends AuthenticatedResource {

    const MAX_LIMIT = 50;

    const KEY_ID = 'id';

    /** @var UGroupManager */
    private $ugroup_manager;

    /** @var UserManager */
    private $user_manager;

    /** @var ProjectManager */
    private $project_manager;

    public function __construct() {
        parent::__construct();
        $this->ugroup_manager  = new UGroupManager();
        $this->user_manager    = UserManager::instance();
        $this->project_manager = ProjectManager::instance();
    }

    /**
     * Get a user_group
     *
     * Get the definition of a given user_group
     *
     * @url GET {id}
     * @access hybrid
     *
     * @param string $id Id of the ugroup This should be one of two formats<br>
     * - format: projectId_ugroupId for dynamic project user groups (project members...)<br>
     * - format: ugroupId for all other groups (registered users, custom groups, ...)
     *
     * @throws 400
     * @throws 403
     * @throws 404
     *
     * @return \Tuleap\Project\REST\UserGroupRepresentation
     */
    public function getId($id) {
        $this->checkAccess();

        $ugroup     = $this->getExistingUserGroup($id);
        $project_id = $ugroup->getProjectId();

        if ($project_id) {
            $this->userCanSeeUserGroups($project_id);
        }

        $ugroup_representation = new UserGroupRepresentation();
        $ugroup_representation->build($project_id, $ugroup);
        $this->sendAllowHeadersForUserGroup();

        return $ugroup_representation;
    }

    /**
     * @url OPTIONS {id}
     *
     * @param string $id Id of the ugroup (format: projectId_ugroupId)
     *
     * @throws 400
     * @throws 403
     * @throws 404
     */
    public function optionsId($id) {
        $this->sendAllowHeadersForUserGroup();
    }

    /**
     * Get users of a user_group
     *
     * Get the users of a given user_group
     *
     * @url GET {id}/users
     * @access protected
     *
     * @param string $id Id of the ugroup This should be one of two formats<br>
     * - format: projectId_ugroupId for dynamic project user groups (project members...)<br>
     * - format: ugroupId for all other groups (registered users, custom groups, ...)
     * @param int $limit  Number of elements displayed per page
     * @param int $offset Position of the first element to display
     *
     * @throws 400
     * @throws 403
     * @throws 404
     * @throws 406
     *
     * @return Array {@type \Tuleap\User\REST\UserRepresentation}
     */
    protected function getUsers($id, $limit = 10, $offset = 0) {
        $this->checkLimitValueIsAcceptable($limit);

        $user_group = $this->getExistingUserGroup($id);
        $this->checkGroupIsViewable($user_group->getId());
        $project_id = $user_group->getProjectId();
        $this->userCanSeeUserGroupMembers($project_id);

        $member_representations = array();
        $members                = $this->getUserGroupMembers($user_group, $project_id, $limit, $offset);

        foreach($members as $member) {
            $member_representations[] = $this->getUserRepresentation($member);
        }

        $this->sendPaginationHeaders($limit, $offset, $this->countUserGroupMembers($user_group, $project_id));
        $this->sendAllowHeadersForUserGroup();

        return $member_representations;
    }

    /**
     * Define users of a user_group
     *
     * Define the users of a given user_group
     * <br><br>
     * Notes on the user reference format. It can be:
     * <ul>
     * <li> {"id": user_id} </li>
     * </ul>
     *
     * @url PUT {id}/users
     *
     * @access protected
     *
     * @param string $id Id of the ugroup This should be one of two formats<br>
     * - format: projectId_ugroupId for dynamic project user groups (project members...)<br>
     * - format: ugroupId for all other groups (registered users, custom groups, ...)
     * @param array $user_references {@from body}
     *
     * @throws 400
     * @throws 404
     */
    protected function putUsers($id, array $user_references) {
        $this->checkAccess();

        $user_group = $this->getExistingUserGroup($id);
        $this->checkUgroupValidity($user_group);

        $project_id = $user_group->getProjectId();
        $this->userCanSeeUserGroupMembers($project_id);

        $this->checkKeysValidity($user_references);

        $user_group->removeAllUsers();
        $this->addMembersToUgroup($user_group, $user_references);
    }

    private function checkUgroupValidity(ProjectUGroup $user_group) {
        if (! $user_group->isStatic()) {
            throw new RestException(400, "Not able to deal with dynamic user group");
        }

        if ($user_group->getSourceGroup() !== null) {
            throw new RestException(400, "Ugroup is bound to a source group");
        }

        $this->checkGroupIsViewable($user_group->getId());
    }

    private function addMembersToUgroup(ProjectUGroup $user_group, $user_references) {
        foreach ($user_references as $user_reference) {
            $key     = key($user_reference);
            $user_id = $user_reference[$key];

            $user = $this->user_manager->getUserById($user_id);

            if (! $user) {
                throw new RestException(400, "User with id $user_id not known");
            }

            $user_group->addUser($user);
        }
    }

    private function checkKeysValidity(array $user_references) {
        if (empty($user_references)) {
            return true;
        }

        $first_key = null;

        foreach ($user_references as $user_reference) {

            if (count(array_keys($user_reference)) > 1) {
                throw new RestException(400, "Only one key can be passed in the representation");
            }

            $key = key($user_reference);

            if ($key !== self::KEY_ID) {
                throw new RestException(400, "key $key not known");
            }

            if ($first_key === null) {
                $first_key = $key;
            } elseif ($first_key !== $key) {
                throw new RestException(400, "ids have not the same type");
            }
        }

        return true;
    }

    /**
     * @url OPTIONS {id}/users
     *
     * @param int $id Id of the ugroup (format: projectId_ugroupId)
     */
    public function optionsUsers($id) {
        $this->sendAllowHeadersForUserGroup();
    }

    /**
     * Get the members of a group
     *
     * @throws 404
     *
     * @return PFUser[]
     */
    private function getUserGroupMembers(ProjectUGroup $user_group, $project_id, $limit, $offset) {
        return $user_group->getStaticOrDynamicMembersPaginated($project_id, $limit, $offset);
    }

    /**
     * Count the members of a group
     *
     * @return int
     */
    private function countUserGroupMembers(ProjectUGroup $user_group, $project_id) {
        return $user_group->countStaticOrDynamicMembers($project_id);
    }

    /**
     * Get the UserRepresentation of a user
     *
     * @param PFUser $member
     *
     * @return \Tuleap\User\REST\UserRepresentation
     */
    private function getUserRepresentation(PFUser $member) {
        $user_representation = new UserRepresentation();
        $user_representation->build($member);

        return $user_representation;
    }

    /**
     * Checks if the given id is appropriate (format: projectId_ugroupId or format: ugroupId)
     *
     * @param string $id Id of the ugroup
     *
     * @return boolean
     *
     * @throws 400
     */
    private function checkIdIsAppropriate($id) {
        try {
            UserGroupRepresentation::checkRESTIdIsAppropriate($id);
        } catch (\Exception $e) {
            throw new RestException(400, $e->getMessage());
        }
    }

    /**
     * Checks if the given user group exists
     *
     * @param int $id
     *
     * @return ProjectUGroup
     *
     * @throws 404
     */
    private function getExistingUserGroup($id) {
        $this->checkIdIsAppropriate($id);

        $values        = UserGroupRepresentation::getProjectAndUserGroupFromRESTId($id);
        $user_group_id = $values['user_group_id'];

        $user_group = $this->ugroup_manager->getById($user_group_id);

        if ($user_group->getId() === 0) {
            throw new RestException(404, 'User Group does not exist');
        }

        if (! $user_group->isStatic()) {
            $user_group->setProjectId($values['project_id']);
        }

        if ($user_group->isStatic() && $values['project_id'] && $values['project_id'] != $user_group->getProjectId()) {
            throw new RestException(404, 'User Group does not exist in project');
        }

        return $user_group;
    }

    /**
     * @throws 403
     * @throws 404
     *
     * @return boolean
     */
    private function userCanSeeUserGroups($project_id) {
        $project      = $this->project_manager->getProject($project_id);
        $user         = $this->user_manager->getCurrentUser();
        ProjectAuthorization::canUserAccessUserGroupInfo($user, $project, new URLVerification());

        return true;
    }

    /**
     * @throws 403
     * @throws 404
     *
     * @return boolean
     */
    private function userCanSeeUserGroupMembers($project_id) {
        $project      = $this->project_manager->getProject($project_id);
        $user         = $this->user_manager->getCurrentUser();
        ProjectAuthorization::userCanAccessProjectAndIsProjectAdmin($user, $project);

        return true;
    }

    /**
     * @param int $ugroup_id
     *
     * @throws 404
     *
     * @return boolean
     */
    private function checkGroupIsViewable($ugroup_id) {
        if (in_array($ugroup_id, ProjectUGroup::$forge_user_groups)) {
            throw new RestException(404, 'Unable to list the users of this group');
        }

        return true;
    }

    private function sendAllowHeadersForUserGroup() {
        Header::allowOptionsGetPut();
    }

    private function sendPaginationHeaders($limit, $offset, $size) {
        Header::sendPaginationHeaders($limit, $offset, $size, self::MAX_LIMIT);
    }

    /**
     * Checks if the limit provided by the request is valid
     *
     * @param int $limit Number of elements displayed per page
     *
     * @return boolean
     *
     * @throws 406
     */

    private function checkLimitValueIsAcceptable($limit) {
        if ($limit > self::MAX_LIMIT) {
             throw new RestException(406, 'limit value is not acceptable');
        }

        return true;
    }
}
