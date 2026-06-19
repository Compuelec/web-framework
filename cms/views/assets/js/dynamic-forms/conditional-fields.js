/**
 * Conditional Fields Handler
 * Evaluates field conditions and shows/hides fields accordingly
 *
 * Condition structure (JSON in conditions_column):
 * {
 *   "operator": "and",  // "and" or "or"
 *   "rules": [
 *     {"field": "field_name", "operator": "equals", "value": "some_value"}
 *   ]
 * }
 *
 * Supported operators:
 * - equals: field value equals the specified value
 * - not_equals: field value does not equal the specified value
 * - empty: field value is empty or null
 * - not_empty: field value is not empty
 */

var ConditionalFields = (function() {
    var moduleData = null;
    var initialized = false;

    /**
     * Initialize conditional fields system
     * @param {Object} module - Module configuration object with columns
     */
    function init(module) {
        if (!module || !module.columns) {
            return;
        }

        moduleData = module;
        initialized = true;

        // Bind change events to all form fields
        bindFieldEvents();

        // Initial evaluation
        evaluateAllConditions();
    }

    /**
     * Bind change/input events to all fields that may trigger condition evaluation
     */
    function bindFieldEvents() {
        if (!moduleData || !moduleData.columns) return;

        moduleData.columns.forEach(function(col) {
            var field = document.getElementById(col.title_column);
            if (field) {
                // Remove existing listeners to prevent duplicates
                field.removeEventListener('change', evaluateAllConditions);
                field.removeEventListener('input', debouncedEvaluate);

                // Add listeners
                field.addEventListener('change', evaluateAllConditions);
                field.addEventListener('input', debouncedEvaluate);
            }
        });

        // Also bind to Select2 elements
        if (typeof $ !== 'undefined' && $.fn.select2) {
            moduleData.columns.forEach(function(col) {
                var $field = $('#' + col.title_column);
                if ($field.length && $field.hasClass('select2-hidden-accessible')) {
                    $field.off('select2:select select2:unselect', evaluateAllConditions);
                    $field.on('select2:select select2:unselect', evaluateAllConditions);
                }
            });
        }
    }

    /**
     * Debounced version of evaluateAllConditions for input events
     */
    var debounceTimer = null;
    function debouncedEvaluate() {
        if (debounceTimer) {
            clearTimeout(debounceTimer);
        }
        debounceTimer = setTimeout(evaluateAllConditions, 300);
    }

    /**
     * Evaluate all field conditions
     */
    function evaluateAllConditions() {
        if (!moduleData || !moduleData.columns) return;

        moduleData.columns.forEach(function(col) {
            if (!col.conditions_column) return;

            var conditions = parseConditions(col.conditions_column);
            if (!conditions || !conditions.rules || conditions.rules.length === 0) return;

            var visible = evaluateConditionGroup(conditions);
            toggleFieldVisibility(col.title_column, visible);
        });
    }

    /**
     * Parse conditions from string or object
     * @param {string|Object} conditionsStr - Conditions JSON string or object
     * @returns {Object|null} Parsed conditions object
     */
    function parseConditions(conditionsStr) {
        if (!conditionsStr) return null;

        try {
            if (typeof conditionsStr === 'string') {
                // Handle URL-encoded strings
                var decoded = conditionsStr;
                try {
                    decoded = decodeURIComponent(conditionsStr);
                } catch (e) {
                    // Use as-is if decoding fails
                }
                return JSON.parse(decoded);
            }
            return conditionsStr;
        } catch (e) {
            console.warn('Could not parse conditions:', conditionsStr, e);
            return null;
        }
    }

    /**
     * Evaluate a group of conditions
     * @param {Object} conditions - Conditions object with operator and rules
     * @returns {boolean} True if conditions are met
     */
    function evaluateConditionGroup(conditions) {
        if (!conditions.rules || conditions.rules.length === 0) return true;

        var results = conditions.rules.map(function(rule) {
            return evaluateRule(rule);
        });

        var operator = conditions.operator || 'and';

        if (operator === 'and') {
            return results.every(Boolean);
        } else {
            return results.some(Boolean);
        }
    }

    /**
     * Evaluate a single condition rule
     * @param {Object} rule - Rule object with field, operator, value
     * @returns {boolean} True if rule is satisfied
     */
    function evaluateRule(rule) {
        if (!rule || !rule.field || !rule.operator) return true;

        var field = document.getElementById(rule.field);
        if (!field) {
            // Field not found, assume condition is met
            return true;
        }

        var fieldValue = getFieldValue(field);

        switch (rule.operator) {
            case 'equals':
                return String(fieldValue) === String(rule.value);

            case 'not_equals':
                return String(fieldValue) !== String(rule.value);

            case 'empty':
                return fieldValue === null || fieldValue === '' || fieldValue === undefined;

            case 'not_empty':
                return fieldValue !== null && fieldValue !== '' && fieldValue !== undefined;

            default:
                return true;
        }
    }

    /**
     * Get the value of a form field
     * @param {HTMLElement} field - Form field element
     * @returns {string} Field value
     */
    function getFieldValue(field) {
        if (!field) return '';

        // Checkbox
        if (field.type === 'checkbox') {
            return field.checked ? '1' : '0';
        }

        // Radio buttons
        if (field.type === 'radio') {
            var checked = document.querySelector('input[name="' + field.name + '"]:checked');
            return checked ? checked.value : '';
        }

        // Select (including multiple)
        if (field.tagName === 'SELECT') {
            if (field.multiple) {
                return Array.from(field.selectedOptions).map(function(opt) {
                    return opt.value;
                }).join(',');
            }
            return field.value;
        }

        // Default (text, textarea, etc.)
        return field.value;
    }

    /**
     * Toggle field visibility based on condition result
     * @param {string} fieldName - Name/ID of the field
     * @param {boolean} visible - Whether field should be visible
     */
    function toggleFieldVisibility(fieldName, visible) {
        var field = document.getElementById(fieldName);
        if (!field) return;

        // Find the parent card containing the field
        var card = field.closest('.card.rounded.border-0.shadow.mb-3');
        if (!card) {
            // Try alternative container
            card = field.closest('.conditional-field-container');
        }
        if (!card) {
            // Last resort: parent element
            card = field.parentElement;
        }

        if (visible) {
            card.style.display = '';
            card.classList.remove('conditional-hidden');
            field.removeAttribute('disabled');
            // Also enable any required validation
            if (field.dataset.wasRequired === 'true') {
                field.setAttribute('required', 'required');
            }
        } else {
            card.style.display = 'none';
            card.classList.add('conditional-hidden');
            field.setAttribute('disabled', 'disabled');
            // Store and remove required attribute
            if (field.hasAttribute('required')) {
                field.dataset.wasRequired = 'true';
                field.removeAttribute('required');
            }
            // Clear field value when hidden (optional, configurable)
            // field.value = '';
        }
    }

    /**
     * Manually trigger condition evaluation
     */
    function evaluate() {
        evaluateAllConditions();
    }

    /**
     * Check if system is initialized
     * @returns {boolean}
     */
    function isInitialized() {
        return initialized;
    }

    /**
     * Get current module data
     * @returns {Object|null}
     */
    function getModuleData() {
        return moduleData;
    }

    // Public API
    return {
        init: init,
        evaluate: evaluate,
        isInitialized: isInitialized,
        getModuleData: getModuleData
    };
})();

// Auto-initialize when DOM is ready
$(document).ready(function() {
    // Try to find module data in the page
    var moduleInput = document.querySelector('input[name="module"]');
    if (moduleInput && moduleInput.value) {
        try {
            var module = JSON.parse(moduleInput.value);
            ConditionalFields.init(module);
        } catch (e) {
            console.warn('Could not auto-initialize conditional fields:', e);
        }
    }
});
