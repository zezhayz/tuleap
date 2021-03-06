angular
    .module('tuleap.frs')
    .service('SharedPropertiesService', SharedPropertiesService);

function SharedPropertiesService() {
    var property = {
        project_id           : null,
        release              : null,
        platform_license_info: null
    };

    return {
        getProjectId          : getProjectId,
        setProjectId          : setProjectId,
        getRelease            : getRelease,
        setRelease            : setRelease,
        getPlatformLicenseInfo: getPlatformLicenseInfo,
        setPlatformLicenseInfo: setPlatformLicenseInfo
    };

    function getProjectId() {
        return property.project_id;
    }

    function setProjectId(project_id) {
        property.project_id = project_id;
    }

    function getRelease() {
        formatLinks();
        return property.release;
    }

    function setRelease(release) {
        property.release = release;
    }

    function setPlatformLicenseInfo(platform_license_info) {
        property.platform_license_info = platform_license_info;
    }

    function getPlatformLicenseInfo() {
        return property.platform_license_info;
    }

    function formatLinks() {
        property.release.links.forEach(function(link) {
            if (link.name) {
                link.displayed_link = link.name;
                return;
            }

            if (link.link.length < 50) {
                link.displayed_link = link.link;
                return;
            }

            link.displayed_link = link.link.substr(0, 23) + '...' + link.link.substr(-23);
        });
    }
}
