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

    if (isNaN(field.value) || field.value.trim() === '') {
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
    if (!regex.test(field.value)) {
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
    const pattern = "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$";
    return validateRegex(errId, msg, fieldId, pattern);
}
