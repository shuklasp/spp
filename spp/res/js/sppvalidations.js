/**
 * sppvalidations.js
 * Client-side validation library for SPP forms.
 */

function _showSppError(id, msg) {
    const el = document.getElementById(id);
    if (el) {
        el.innerHTML = msg;
        el.style.display = 'block';
        el.classList.add('errormsg');
    }
}

function _clearSppError(id) {
    const el = document.getElementById(id);
    if (el) {
        el.innerHTML = '';
        el.style.display = 'none';
        el.classList.remove('errormsg');
    }
}

function validateRequired(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    if (field.value.trim() === '') {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateNumeric(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    if (field.value.trim() !== '' && (isNaN(field.value) || isNaN(parseFloat(field.value)))) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateMinLength(errId, msg, fieldId, min) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    if (field.value.trim() !== '' && field.value.length < min) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateMaxLength(errId, msg, fieldId, max) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    if (field.value.trim() !== '' && field.value.length > max) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateEmail(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    if (field.value.trim() !== '' && !emailRegex.test(field.value)) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateRegex(errId, msg, fieldId, pattern) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const regex = new RegExp(pattern);
    if (field.value.trim() !== '' && !regex.test(field.value)) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateUrl(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const urlRegex = /^(https?:\/\/)?([\da-z\.-]+)\.([a-z\.]{2,6})([\/\w \.-]*)*\/?$/;
    if (field.value.trim() !== '' && !urlRegex.test(field.value)) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateRange(errId, msg, fieldId, min, max) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const val = parseFloat(field.value);
    if (field.value.trim() !== '' && (isNaN(val) || val < min || val > max)) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateMatch(errId, msg, fieldId, targetId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    const target = document.getElementById(targetId) || document.getElementsByName(targetId)[0];
    if (!field || !target) return true;

    if (field.value !== target.value) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateInArray(errId, msg, fieldId, optionsJson) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const options = JSON.parse(optionsJson);
    if (field.value.trim() !== '' && !options.includes(field.value)) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

function validateJson(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    if (field.value.trim() !== '') {
        try {
            JSON.parse(field.value);
            _clearSppError(errId);
            field.classList.remove('errorclass');
            return true;
        } catch (e) {
            _showSppError(errId, msg);
            field.classList.add('errorclass');
            return false;
        }
    }
    return true;
}

function validateCreditCard(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    let value = field.value.replace(/\D/g, '');
    if (value === '') return true;

    let sum = 0;
    let shouldDouble = false;
    for (let i = value.length - 1; i >= 0; i--) {
        let digit = parseInt(value.charAt(i));
        if (shouldDouble) {
            if ((digit *= 2) > 9) digit -= 9;
        }
        sum += digit;
        shouldDouble = !shouldDouble;
    }

    if (sum % 10 !== 0) {
        _showSppError(errId, msg);
        field.classList.add('errorclass');
        return false;
    } else {
        _clearSppError(errId);
        field.classList.remove('errorclass');
        return true;
    }
}

/** 
 * India Specific Validators
 */

function validatePan(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    const regex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
    if (field.value.trim() !== '' && !regex.test(field.value.toUpperCase())) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateGstin(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    const regex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
    if (field.value.trim() !== '' && !regex.test(field.value.toUpperCase())) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateIfsc(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    const regex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
    if (field.value.trim() !== '' && !regex.test(field.value.toUpperCase())) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateAadhaar(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    let value = field.value.replace(/\s/g, '');
    if (value === '') return true;

    if (value.length !== 12 || isNaN(value)) {
        _showSppError(errId, msg);
        return false;
    }

    // Verhoeff Algorithm check
    const d = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6], [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1], [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4], [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
    ];
    const p = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2], [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1], [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
    ];
    const inv = [0, 4, 3, 2, 1, 5, 6, 7, 8, 9];

    let c = 0;
    let invertedArray = value.split('').map(Number).reverse();
    for (let i = 0; i < invertedArray.length; i++) {
        c = d[c][p[i % 8][invertedArray[i]]];
    }
    
    if (c !== 0) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validatePincode(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    const regex = /^[1-9][0-9]{5}$/;
    if (field.value.trim() !== '' && !regex.test(field.value)) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateIndiaMobile(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;
    const regex = /^[6-9]\d{9}$/;
    if (field.value.trim() !== '' && !regex.test(field.value)) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateDateAfter(errId, msg, fieldId, targetId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    const target = document.getElementById(targetId) || document.getElementsByName(targetId)[0];
    if (!field || !target || field.value === '' || target.value === '') return true;

    const date = new Date(field.value);
    const targetDate = new Date(target.value);
    if (date <= targetDate) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validatePasswordStrength(errId, msg, fieldId, minScore) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field || field.value === '') return true;

    let score = 0;
    const val = field.value;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[a-z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    if (score < minScore) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateIban(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field || field.value === '') return true;

    const iban = field.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const regex = /^[A-Z]{2}[0-9]{2}[A-Z0-9]{4,30}$/;
    if (!regex.test(iban)) {
        _showSppError(errId, msg);
        return false;
    }

    // Rearrange and convert to numbers for MOD 97 check
    const rearranged = iban.slice(4) + iban.slice(0, 4);
    let numeric = '';
    for (let i = 0; i < rearranged.length; i++) {
        const charCode = rearranged.charCodeAt(i);
        if (charCode >= 65 && charCode <= 90) {
            numeric += (charCode - 55).toString();
        } else {
            numeric += rearranged[i];
        }
    }

    // BigInt for MOD 97
    if (BigInt(numeric) % 97n !== 1n) {
        _showSppError(errId, msg);
        return false;
    }

    _clearSppError(errId);
    return true;
}

function validateRequiredIf(errId, msg, fieldId, targetId, targetValue) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    const target = document.getElementById(targetId) || document.getElementsByName(targetId)[0];
    if (!field || !target) return true;

    if (target.value === targetValue && field.value.trim() === '') {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateGreaterThan(errId, msg, fieldId, targetId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    const target = document.getElementById(targetId) || document.getElementsByName(targetId)[0];
    if (!field || !target || field.value === '' || target.value === '') return true;

    if (parseFloat(field.value) <= parseFloat(target.value)) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateIp(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field || field.value === '') return true;

    const ipv4 = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
    const ipv6 = /^(?:[A-F0-9]{1,4}:){7}[A-F0-9]{1,4}$/i;
    if (!ipv4.test(field.value) && !ipv6.test(field.value)) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateMacAddress(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field || field.value === '') return true;

    const regex = /^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/;
    if (!regex.test(field.value)) {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateIsbn(errId, msg, fieldId) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field || field.value === '') return true;

    const isbn = field.value.replace(/[- ]/g, '');
    if (isbn.length === 10) {
        // ISBN-10 check
        let sum = 0;
        for (let i = 0; i < 9; i++) sum += (10 - i) * parseInt(isbn[i]);
        let last = isbn[9].toUpperCase() === 'X' ? 10 : parseInt(isbn[9]);
        if ((sum + last) % 11 !== 0) {
            _showSppError(errId, msg);
            return false;
        }
    } else if (isbn.length === 13) {
        // ISBN-13 check
        let sum = 0;
        for (let i = 0; i < 12; i++) sum += (i % 2 === 0 ? 1 : 3) * parseInt(isbn[i]);
        if ((10 - (sum % 10)) % 10 !== parseInt(isbn[12])) {
            _showSppError(errId, msg);
            return false;
        }
    } else {
        _showSppError(errId, msg);
        return false;
    }
    _clearSppError(errId);
    return true;
}

function validateCustomRule(errId, msg, fieldId, clientRule) {
    const field = document.getElementById(fieldId) || document.getElementsByName(fieldId)[0];
    if (!field) return true;

    const val = field.value !== undefined ? String(field.value) : '';
    let ruleObj = clientRule;
    if (typeof clientRule === 'string') {
        try { ruleObj = JSON.parse(clientRule); } catch (e) { ruleObj = {}; }
    }

    if (ruleObj.required && val.trim() === '') {
        _showSppError(errId, msg || ruleObj['data-msg'] || 'This field is required.');
        field.classList.add('errorclass');
        return false;
    }

    if (val.trim() !== '') {
        if (ruleObj.minlength && val.length < ruleObj.minlength) {
            _showSppError(errId, msg || ruleObj['data-msg'] || `Must be at least ${ruleObj.minlength} characters.`);
            field.classList.add('errorclass');
            return false;
        }

        if (ruleObj.maxlength && val.length > ruleObj.maxlength) {
            _showSppError(errId, msg || ruleObj['data-msg'] || `Must not exceed ${ruleObj.maxlength} characters.`);
            field.classList.add('errorclass');
            return false;
        }

        if (ruleObj.pattern) {
            const regex = new RegExp(ruleObj.pattern);
            if (!regex.test(val)) {
                _showSppError(errId, msg || ruleObj['data-msg'] || 'Invalid format.');
                field.classList.add('errorclass');
                return false;
            }
        }

        const customRuleName = ruleObj['data-rule'];
        if (customRuleName && typeof window !== 'undefined' && window.SPPValidator) {
            if (!window.SPPValidator.validate(customRuleName, val, ruleObj, field)) {
                _showSppError(errId, msg || ruleObj['data-msg'] || 'Invalid value.');
                field.classList.add('errorclass');
                return false;
            }
        }
    }

    _clearSppError(errId);
    field.classList.remove('errorclass');
    return true;
}

const SPPValidator = {
    _rules: {},

    register(name, fn) {
        this._rules[name.toLowerCase()] = fn;
    },

    has(name) {
        return !!this._rules[name.toLowerCase()];
    },

    validate(name, value, params = {}, el = null) {
        const fn = this._rules[name.toLowerCase()];
        if (typeof fn === 'function') {
            return fn(value, params, el);
        }
        return true;
    },

    getRules() {
        return this._rules;
    },

    /**
     * Attach validators declared in a Form YAML to an HTML form on the page.
     * Matches fields by name or id, applying client constraints, live checks, and submit guards.
     */
    async bindForm(formElementOrId, schema = null) {
        const form = typeof formElementOrId === 'string' 
            ? document.getElementById(formElementOrId) || document.querySelector(`form[data-spp-form="${formElementOrId}"]`) || document.querySelector(`form[name="${formElementOrId}"]`)
            : formElementOrId;
        if (!form || !(form instanceof HTMLFormElement)) return null;

        const formId = form.getAttribute('data-spp-form') || form.id || form.getAttribute('name');
        if (!formId) return null;

        if (form.__spp_validation_bound) return form;
        form.__spp_validation_bound = true;

        // 1. Resolve schema from script tag if pre-rendered
        let formSchema = schema;
        if (!formSchema) {
            const scriptEl = document.getElementById(`spp-form-schema-${formId}`);
            if (scriptEl && scriptEl.textContent) {
                try { formSchema = JSON.parse(scriptEl.textContent); } catch (e) {}
            }
        }

        // 2. Fallback to API if not present in DOM
        if (!formSchema && typeof fetch === 'function') {
            try {
                const apiEndpoint = window.SPP_API_ENDPOINT || 'api.php';
                const fd = new FormData();
                fd.append('action', 'get_form_validators');
                fd.append('form_id', formId);
                const app = form.getAttribute('data-spp-app') || (typeof window !== 'undefined' && window.admin?.selectedApp) || (typeof window !== 'undefined' && window.SPP_ACTIVE_APP) || '';
                if (app) fd.append('appname', app);
                const res = await fetch(apiEndpoint, { method: 'POST', body: fd });
                if (res.ok) {
                    const json = await res.json();
                    if (json.success && json.data) {
                        formSchema = json.data;
                    }
                }
            } catch (e) {
                console.warn(`[SPPValidator] Could not fetch schema for form '${formId}':`, e);
            }
        }

        if (!formSchema || !formSchema.fields) return form;

        const fields = formSchema.fields;

        function setFieldError(fieldEl, errorMsg) {
            let errContainer = form.querySelector(`#err_${fieldEl.id || fieldEl.name}`) ||
                               form.querySelector(`.spp-field-error[data-for="${fieldEl.name}"]`) ||
                               form.querySelector(`.spp-field-error[data-for="${fieldEl.id}"]`);

            if (errorMsg) {
                fieldEl.classList.add('errorclass', 'is-invalid');
                fieldEl.setAttribute('aria-invalid', 'true');
                if (!errContainer) {
                    errContainer = document.createElement('div');
                    errContainer.className = 'spp-field-error';
                    errContainer.setAttribute('data-for', fieldEl.name || fieldEl.id);
                    errContainer.style.cssText = 'color: #ef4444; font-size: 0.78rem; margin-top: 4px; font-weight: 500; display: flex; align-items: center; gap: 4px;';
                    if (fieldEl.nextSibling) {
                        fieldEl.parentNode.insertBefore(errContainer, fieldEl.nextSibling);
                    } else {
                        fieldEl.parentNode.appendChild(errContainer);
                    }
                }
                const safeMsg = String(errorMsg).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                errContainer.innerHTML = `<span>⚠️</span> <span>${safeMsg}</span>`;
                errContainer.style.display = 'flex';
            } else {
                fieldEl.classList.remove('errorclass', 'is-invalid');
                fieldEl.removeAttribute('aria-invalid');
                if (errContainer) {
                    errContainer.innerHTML = '';
                    errContainer.style.display = 'none';
                }
            }
        }

        function validateField(fieldEl, fieldDef) {
            const val = fieldEl.value !== undefined ? String(fieldEl.value).trim() : '';
            const cr = fieldDef.client_rule || {};
            const label = fieldDef.label || fieldEl.name || 'Field';

            if (cr.required && val === '') {
                const msg = (cr['data-msg'] || `${label} is required.`).replace(':attribute', label);
                setFieldError(fieldEl, msg);
                return false;
            }

            if (val !== '') {
                if (cr.minlength && val.length < cr.minlength) {
                    const msg = `${label} must be at least ${cr.minlength} characters.`;
                    setFieldError(fieldEl, msg);
                    return false;
                }
                if (cr.maxlength && val.length > cr.maxlength) {
                    const msg = `${label} must not exceed ${cr.maxlength} characters.`;
                    setFieldError(fieldEl, msg);
                    return false;
                }
                if (cr.pattern) {
                    const regex = new RegExp(cr.pattern);
                    if (!regex.test(val)) {
                        const msg = (cr['data-msg'] || `${label} format is invalid.`).replace(':attribute', label);
                        setFieldError(fieldEl, msg);
                        return false;
                    }
                }
                const customRuleName = cr['data-rule'];
                if (customRuleName && SPPValidator.has(customRuleName)) {
                    if (!SPPValidator.validate(customRuleName, val, cr, fieldEl)) {
                        const msg = (cr['data-msg'] || `${label} is invalid.`).replace(':attribute', label);
                        setFieldError(fieldEl, msg);
                        return false;
                    }
                }
            }

            setFieldError(fieldEl, null);
            return true;
        }

        // 1. Initial entity hydration (if editing)
        if (formSchema.initial && typeof formSchema.initial === 'object') {
            for (const [key, val] of Object.entries(formSchema.initial)) {
                const fieldEl = form.querySelector(`[name="${key}"]`) || form.querySelector(`#${key}`);
                if (fieldEl && (fieldEl.value === '' || fieldEl.value === undefined)) {
                    if (fieldEl.type === 'checkbox') {
                        fieldEl.checked = Boolean(val);
                    } else if (fieldEl.type === 'radio') {
                        const radio = form.querySelector(`[name="${key}"][value="${val}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        fieldEl.value = val;
                    }
                }
            }
        }

        // 2. Old input hydration (after validation redirect)
        if (formSchema.old && typeof formSchema.old === 'object') {
            for (const [key, val] of Object.entries(formSchema.old)) {
                const fieldEl = form.querySelector(`[name="${key}"]`) || form.querySelector(`#${key}`);
                if (fieldEl) {
                    if (fieldEl.type === 'checkbox') {
                        fieldEl.checked = Boolean(val);
                    } else if (fieldEl.type === 'radio') {
                        const radio = form.querySelector(`[name="${key}"][value="${val}"]`);
                        if (radio) radio.checked = true;
                    } else {
                        fieldEl.value = val;
                    }
                }
            }
        }

        // 3. Server errors display (after validation redirect)
        if (formSchema.errors && typeof formSchema.errors === 'object') {
            for (const [key, msgs] of Object.entries(formSchema.errors)) {
                const fieldEl = form.querySelector(`[name="${key}"]`) || form.querySelector(`#${key}`);
                if (fieldEl) {
                    const msg = Array.isArray(msgs) ? msgs[0] : String(msgs);
                    setFieldError(fieldEl, msg);
                }
            }
        }

        // Attach listeners and HTML5 attributes to inputs
        for (const [fieldName, fieldDef] of Object.entries(fields)) {
            const fieldEl = form.querySelector(`[name="${fieldName}"]`) || 
                            form.querySelector(`#${fieldName}`) || 
                            form.querySelector(`[data-field="${fieldName}"]`);
            if (!fieldEl) continue;

            const cr = fieldDef.client_rule || {};
            if (cr.required) fieldEl.required = true;
            if (cr.pattern) fieldEl.pattern = cr.pattern;
            if (cr.minlength) fieldEl.minLength = cr.minlength;
            if (cr.maxlength) fieldEl.maxLength = cr.maxlength;
            if (cr['data-rule']) fieldEl.setAttribute('data-spp-rule', cr['data-rule']);
            if (cr['data-msg']) fieldEl.setAttribute('data-msg', cr['data-msg']);

            fieldEl.addEventListener('input', () => validateField(fieldEl, fieldDef));
            fieldEl.addEventListener('blur', () => validateField(fieldEl, fieldDef));

            const rawRules = fieldDef.validation || fieldDef.rules || fieldDef.rule || '';
            const isRemote = (typeof rawRules === 'string' && (rawRules.includes('unique') || rawRules.includes('exists') || rawRules.includes('remote'))) || fieldEl.hasAttribute('data-spp-remote');

            if (isRemote) {
                let debounceTimer = null;
                const checkRemote = async () => {
                    if (!validateField(fieldEl, fieldDef)) return;
                    const val = fieldEl.value !== undefined ? String(fieldEl.value).trim() : '';
                    if (!val) return;
                    try {
                        const app = form.getAttribute('data-spp-app') || (typeof window !== 'undefined' && window.admin?.selectedApp) || (typeof window !== 'undefined' && window.SPP_ACTIVE_APP) || '';
                        const apiEndpoint = window.SPP_API_ENDPOINT || 'api.php';
                        const fd = new FormData();
                        fd.append('action', 'validate_field');
                        fd.append('field', fieldName);
                        fd.append('value', val);
                        fd.append('form_id', formId);
                        if (rawRules) fd.append('rules', rawRules);
                        if (app) fd.append('appname', app);

                        const res = await fetch(apiEndpoint, { method: 'POST', body: fd });
                        if (res.ok) {
                            const json = await res.json();
                            if (json && json.valid === false) {
                                const msg = json.message || `${fieldDef.label || fieldName} is invalid.`;
                                setFieldError(fieldEl, msg);
                                fieldEl.__spp_remote_valid = false;
                            } else if (json && json.valid === true) {
                                fieldEl.__spp_remote_valid = true;
                            }
                        }
                    } catch (e) {
                        // Silent catch on network blip
                    }
                };

                fieldEl.addEventListener('input', () => {
                    clearTimeout(debounceTimer);
                    debounceTimer = setTimeout(checkRemote, 400);
                });
                fieldEl.addEventListener('blur', () => {
                    clearTimeout(debounceTimer);
                    checkRemote();
                });
            }
        }

        form.sppValidate = () => {
            let isValid = true;
            for (const [fieldName, fieldDef] of Object.entries(fields)) {
                const fieldEl = form.querySelector(`[name="${fieldName}"]`) || 
                                form.querySelector(`#${fieldName}`) || 
                                form.querySelector(`[data-field="${fieldName}"]`);
                if (!fieldEl) continue;
                if (!validateField(fieldEl, fieldDef)) isValid = false;
            }
            return isValid;
        };

        form.sppReset = () => {
            for (const fieldName of Object.keys(fields)) {
                const fieldEl = form.querySelector(`[name="${fieldName}"]`) || 
                                form.querySelector(`#${fieldName}`) || 
                                form.querySelector(`[data-field="${fieldName}"]`);
                if (fieldEl) setFieldError(fieldEl, null);
            }
            form.reset();
        };

        // Intercept form submit
        form.addEventListener('submit', (e) => {
            let firstInvalid = null;
            let isValid = true;

            for (const [fieldName, fieldDef] of Object.entries(fields)) {
                const fieldEl = form.querySelector(`[name="${fieldName}"]`) || 
                                form.querySelector(`#${fieldName}`) || 
                                form.querySelector(`[data-field="${fieldName}"]`);
                if (!fieldEl) continue;

                const passed = validateField(fieldEl, fieldDef);
                if (!passed) {
                    isValid = false;
                    if (!firstInvalid) firstInvalid = fieldEl;
                }
            }

            if (!isValid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                if (firstInvalid && typeof firstInvalid.focus === 'function') {
                    firstInvalid.focus();
                }
                form.dispatchEvent(new CustomEvent('spp:form:invalid', { detail: { formId, schema: formSchema } }));
            }
        });

        return form;
    },

    /**
     * Auto-discover and bind all forms in the document container.
     */
    autoBindAllForms(container = document) {
        if (!container || typeof container.querySelectorAll !== 'function') return;

        // 1. All forms with data-spp-form
        container.querySelectorAll('form[data-spp-form]').forEach(form => {
            this.bindForm(form);
        });

        // 2. All forms with matching schema scripts in the DOM
        container.querySelectorAll('script[type="application/json"][id^="spp-form-schema-"]').forEach(scriptEl => {
            const formId = scriptEl.id.replace('spp-form-schema-', '');
            const form = container.querySelector(`form#${formId}`) || container.querySelector(`form[name="${formId}"]`);
            if (form) {
                this.bindForm(form);
            }
        });
    }
};

if (typeof window !== 'undefined') {
    window.SPPValidator = SPPValidator;
    window.validateCustomRule = validateCustomRule;

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => SPPValidator.autoBindAllForms());
        } else {
            setTimeout(() => SPPValidator.autoBindAllForms(), 50);
        }
    }
}

