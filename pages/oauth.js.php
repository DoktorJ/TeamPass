<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      oauth.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('oauth') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    /**
     * TOP MENU BUTTONS ACTIONS
     */
    $(document).on('click', '.tp-action', function() {
        console.log($(this).data('action'))
        $('#ldap-test-config-results-text').html('');
        if ($(this).data('action') === 'ldap-test-config') {
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {
                'username': $('#ldap-test-config-username').val(),
                'password': $('#ldap-test-config-pwd').val(),
            }

            $.post(
                "sources/ldap.queries.php", {
                    type: "ldap_test_configuration",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(data);

                    if (data.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                //timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#ldap-test-config-results-text').html(data.message);
                        $('#ldap-test-config-results').removeClass('hidden');

                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
            // ---
            // END
            // ---
        }
    });

    /**
     * On page loaded
     */
    $(function() {
        let isProgrammaticChange = false;
        // Function to update oauth2_client_endpoint
        function updateClientEndpoint(tenantId) {
            var endpointUrl = $('#oauth2_client_endpoint').val();
            endpointUrl = endpointUrl.replace(/([^\/]*)\/oauth2\/v2.0\/authorize/, tenantId + '/oauth2/v2.0/authorize');
            $('#oauth2_client_endpoint').val(endpointUrl).trigger('change');
        }

        // Function to update oauth2_client_token
        function updateClientToken(tenantId) {
            var tokenUrl = $('#oauth2_client_token').val();
            tokenUrl = tokenUrl.replace(/([^\/]*)\/oauth2\/v2.0\/token/, tenantId + '/oauth2/v2.0/token');
            $('#oauth2_client_token').val(tokenUrl).trigger('change');
        }

        // Fonction pour mettre à jour oauth2_tenant_id
        // L'utilisation des précédents triggers bloque la propagation des événements
        function updateTenantId() {
            $('#oauth2_tenant_id').trigger('change');
        }

        // Launch the change event in DB
        $('#oauth2_tenant_id').change(function(event) {
            if (isProgrammaticChange) return;
            var tenantId = $(this).val();
            updateClientEndpoint(tenantId);
            setTimeout(function() {
                updateClientToken(tenantId);

                isProgrammaticChange = true;
                setTimeout(function() {
                    updateTenantId();
                    isProgrammaticChange = false;
                }, 100);
                
            }, 100);
        });
    });

    //]]>
</script>
