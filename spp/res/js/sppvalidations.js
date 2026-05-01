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
