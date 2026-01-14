Shopware.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'accessory_requirement',
        roles: {
            viewer: {
                privileges: [
                    'accessory_requirement:read',
                    'product:read'
                ],
                dependencies: []
            },
            editor: {
                privileges: [
                    'accessory_requirement:create',
                    'accessory_requirement:update',
                    'accessory_requirement:delete'
                ],
                dependencies: [
                    'accessory_requirement.viewer'
                ]
            },
        }
    });
