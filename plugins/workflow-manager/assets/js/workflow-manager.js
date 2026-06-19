/**
 * Workflow Manager Plugin JavaScript
 */

(function() {
    'use strict';

    // Plugin state
    var state = {
        currentModuleId: null,
        currentModuleName: '',
        workflow: null,
        roles: [],
        states: [],
        transitions: []
    };

    // Get plugin AJAX URL
    var cmsBasePath = window.CMS_BASE_PATH || '';
    var projectBasePath = cmsBasePath.replace(/\/cms$/, '');
    var pluginUrl = projectBasePath + '/plugins/workflow-manager/ajax.php';

    /**
     * Initialize plugin
     */
    function init() {
        loadModules();
        loadRoles();
        bindEvents();
    }

    /**
     * Load modules with workflow fields
     */
    function loadModules() {
        $.ajax({
            url: pluginUrl,
            method: 'POST',
            data: { ajax_action: 'get_modules' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderModulesList(response.modules);
                } else {
                    showError('Error loading modules: ' + response.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('WorkflowManager: AJAX error:', status, error);
                console.error('WorkflowManager: Response text:', xhr.responseText);
                showError('Error connecting to server');
            }
        });
    }

    /**
     * Load available roles
     */
    function loadRoles() {
        $.ajax({
            url: pluginUrl,
            method: 'POST',
            data: { ajax_action: 'get_roles' },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    state.roles = response.roles;
                }
            }
        });
    }

    /**
     * Render modules list
     */
    function renderModulesList(modules) {
        var container = $('#modules-list');
        container.empty();

        if (modules.length === 0) {
            container.html('<div class="text-center py-4 text-muted"><i class="bi bi-inbox display-4"></i><p class="mt-2">No hay modulos con campos workflow</p></div>');
            return;
        }

        modules.forEach(function(module) {
            var item = $('<a href="#" class="list-group-item list-group-item-action"></a>');
            item.attr('data-module-id', module.id_module);
            item.attr('data-module-name', module.alias_module);
            item.html(
                '<div class="d-flex justify-content-between align-items-center">' +
                    '<div>' +
                        '<strong>' + module.alias_module + '</strong>' +
                        '<br><small class="text-muted">' + module.title_module + '</small>' +
                    '</div>' +
                    '<i class="bi bi-chevron-right text-muted"></i>' +
                '</div>'
            );
            container.append(item);
        });
    }

    /**
     * Load workflow for a module
     */
    function loadWorkflow(moduleId) {
        $.ajax({
            url: pluginUrl,
            method: 'POST',
            data: {
                ajax_action: 'get_workflow',
                module_id: moduleId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    state.workflow = response.workflow;
                    renderWorkflow();
                } else {
                    showError('Error loading workflow: ' + response.error);
                }
            },
            error: function() {
                showError('Error connecting to server');
            }
        });
    }

    /**
     * Render workflow editor
     */
    function renderWorkflow() {
        $('#no-module-selected').hide();
        $('#workflow-editor').show();

        // Render states
        renderStates();

        // Render transitions
        renderTransitions();

        // Render settings
        renderSettings();
    }

    /**
     * Render states list
     */
    function renderStates() {
        var container = $('#states-container');
        container.empty();

        var states = state.workflow.states || [];
        state.states = states;

        states.forEach(function(stateItem, index) {
            var template = $('#state-template').html();
            var element = $(template);

            element.attr('data-state-index', index);
            element.find('.state-color').val(stateItem.color || '#6c757d');
            element.find('.state-id').val(stateItem.id || '');
            element.find('.state-label').val(stateItem.label || '');

            container.append(element);
        });

        // Update initial state dropdown
        updateInitialStateOptions();
        updateTransitionStateOptions();
    }

    /**
     * Render transitions list
     */
    function renderTransitions() {
        var container = $('#transitions-container');
        container.empty();

        var transitions = state.workflow.transitions || [];
        state.transitions = transitions;

        transitions.forEach(function(transition, index) {
            addTransitionItem(transition, index);
        });
    }

    /**
     * Add a transition item to the list
     */
    function addTransitionItem(transition, index) {
        var container = $('#transitions-container');
        var template = $('#transition-template').html();
        var element = $(template);

        element.attr('data-transition-index', index);
        element.find('.transition-id').val(transition.id || '');
        element.find('.transition-label').val(transition.label || '');
        element.find('.transition-require-comment').prop('checked', transition.require_comment || false);

        // Populate from select
        var fromSelect = element.find('.transition-from');
        state.states.forEach(function(s) {
            var selected = (transition.from || []).indexOf(s.id) !== -1;
            fromSelect.append('<option value="' + s.id + '"' + (selected ? ' selected' : '') + '>' + s.label + '</option>');
        });

        // Populate to select
        var toSelect = element.find('.transition-to');
        state.states.forEach(function(s) {
            var selected = transition.to === s.id;
            toSelect.append('<option value="' + s.id + '"' + (selected ? ' selected' : '') + '>' + s.label + '</option>');
        });

        // Populate roles select
        var rolesSelect = element.find('.transition-roles');
        state.roles.forEach(function(role) {
            var roleLabel = role === '*' ? 'Todos (*)' : role;
            var selected = (transition.roles || []).indexOf(role) !== -1;
            rolesSelect.append('<option value="' + role + '"' + (selected ? ' selected' : '') + '>' + roleLabel + '</option>');
        });

        container.append(element);
    }

    /**
     * Render settings
     */
    function renderSettings() {
        var settings = state.workflow.settings || {};

        // Update initial state dropdown
        $('#initial-state').val(settings.initial_state || 'draft');

        // Update log transitions
        $('#log-transitions').val(settings.log_transitions !== false ? 'true' : 'false');
    }

    /**
     * Update initial state dropdown options
     */
    function updateInitialStateOptions() {
        var select = $('#initial-state');
        var currentValue = select.val();
        select.empty();

        state.states.forEach(function(s) {
            select.append('<option value="' + s.id + '">' + s.label + '</option>');
        });

        if (currentValue) {
            select.val(currentValue);
        }
    }

    /**
     * Update transition state options
     */
    function updateTransitionStateOptions() {
        $('.transition-from, .transition-to').each(function() {
            var select = $(this);
            var isMultiple = select.hasClass('transition-from');
            var currentValues = select.val();

            select.empty();
            state.states.forEach(function(s) {
                select.append('<option value="' + s.id + '">' + s.label + '</option>');
            });

            if (currentValues) {
                select.val(currentValues);
            }
        });
    }

    /**
     * Collect current states from UI
     */
    function collectStates() {
        var states = [];
        $('#states-container .state-item').each(function() {
            var item = $(this);
            states.push({
                id: item.find('.state-id').val().trim(),
                label: item.find('.state-label').val().trim(),
                color: item.find('.state-color').val()
            });
        });
        return states;
    }

    /**
     * Collect current transitions from UI
     */
    function collectTransitions() {
        var transitions = [];
        $('#transitions-container .transition-item').each(function() {
            var item = $(this);
            transitions.push({
                id: item.find('.transition-id').val().trim(),
                from: item.find('.transition-from').val() || [],
                to: item.find('.transition-to').val(),
                label: item.find('.transition-label').val().trim(),
                roles: item.find('.transition-roles').val() || [],
                require_comment: item.find('.transition-require-comment').is(':checked')
            });
        });
        return transitions;
    }

    /**
     * Collect settings from UI
     */
    function collectSettings() {
        return {
            initial_state: $('#initial-state').val(),
            log_transitions: $('#log-transitions').val() === 'true'
        };
    }

    /**
     * Save workflow
     */
    function saveWorkflow() {
        var states = collectStates();
        var transitions = collectTransitions();
        var settings = collectSettings();

        // Validate
        if (states.length === 0) {
            showError('Debe agregar al menos un estado');
            return;
        }

        for (var i = 0; i < states.length; i++) {
            if (!states[i].id || !states[i].label) {
                showError('Todos los estados deben tener ID y etiqueta');
                return;
            }
        }

        var btn = $('#btn-save-workflow');
        btn.addClass('saving').html('<span class="spinner-border spinner-border-sm me-2"></span>Guardando...');

        $.ajax({
            url: pluginUrl,
            method: 'POST',
            data: {
                ajax_action: 'save_workflow',
                module_id: state.currentModuleId,
                states: JSON.stringify(states),
                transitions: JSON.stringify(transitions),
                settings: JSON.stringify(settings)
            },
            dataType: 'json',
            success: function(response) {
                btn.removeClass('saving').html('<i class="bi bi-check-circle me-2"></i>Guardar Workflow');

                if (response.success) {
                    showSuccess('Workflow guardado correctamente');
                    // Update local state
                    state.states = states;
                    state.transitions = transitions;
                } else {
                    showError('Error: ' + response.error);
                }
            },
            error: function() {
                btn.removeClass('saving').html('<i class="bi bi-check-circle me-2"></i>Guardar Workflow');
                showError('Error de conexion');
            }
        });
    }

    /**
     * Bind events
     */
    function bindEvents() {
        // Module selection
        $(document).on('click', '#modules-list .list-group-item', function(e) {
            e.preventDefault();
            var moduleId = $(this).data('module-id');
            var moduleName = $(this).data('module-name');

            $('#modules-list .list-group-item').removeClass('active');
            $(this).addClass('active');

            state.currentModuleId = moduleId;
            state.currentModuleName = moduleName;

            loadWorkflow(moduleId);
        });

        // Add state
        $('#btn-add-state').on('click', function() {
            var template = $('#state-template').html();
            var element = $(template);
            var index = $('#states-container .state-item').length;

            element.attr('data-state-index', index);
            $('#states-container').append(element);

            // Update state array
            state.states.push({ id: '', label: '', color: '#6c757d' });
        });

        // Delete state
        $(document).on('click', '.btn-delete-state', function() {
            var item = $(this).closest('.state-item');
            var index = item.data('state-index');

            item.remove();
            state.states.splice(index, 1);

            // Re-index remaining items
            $('#states-container .state-item').each(function(i) {
                $(this).attr('data-state-index', i);
            });

            updateInitialStateOptions();
            updateTransitionStateOptions();
        });

        // State change - update dropdowns
        $(document).on('change', '.state-id, .state-label', function() {
            state.states = collectStates();
            updateInitialStateOptions();
            updateTransitionStateOptions();
        });

        // Add transition
        $('#btn-add-transition').on('click', function() {
            var newTransition = {
                id: '',
                from: [],
                to: '',
                label: '',
                roles: ['*'],
                require_comment: false
            };

            var index = $('#transitions-container .transition-item').length;
            addTransitionItem(newTransition, index);
            state.transitions.push(newTransition);
        });

        // Delete transition
        $(document).on('click', '.btn-delete-transition', function() {
            var item = $(this).closest('.transition-item');
            var index = item.data('transition-index');

            item.remove();
            state.transitions.splice(index, 1);

            // Re-index remaining items
            $('#transitions-container .transition-item').each(function(i) {
                $(this).attr('data-transition-index', i);
            });
        });

        // Save workflow
        $('#btn-save-workflow').on('click', function() {
            saveWorkflow();
        });
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        if (typeof fncSweetAlert === 'function') {
            fncSweetAlert('success', message, '');
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: message, timer: 2000, showConfirmButton: false });
        } else {
            alert(message);
        }
    }

    /**
     * Show error message
     */
    function showError(message) {
        if (typeof fncSweetAlert === 'function') {
            fncSweetAlert('error', 'Error', message);
        } else if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Error', text: message });
        } else {
            alert('Error: ' + message);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        init();
    });

})();
