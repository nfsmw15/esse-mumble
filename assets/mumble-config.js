/**
 * esse-mumble — Config-Page JS (Namens-Preset-Logik)
 *
 * Copyright (C) 2026 Andreas P. <https://nfsmw15.de>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
(function () {
    'use strict';

    var PRESETS_USER = {
        'default':  '',
        'alphanum': '[A-Za-z0-9_\\-\\.]+',
        'ascii':    '[\\x21-\\x7E]+'
    };

    var PRESETS_CHAN = {
        'default':  '',
        'alphanum': '[A-Za-z0-9 _\\-\\.]+',
        'ascii':    '[\\x20-\\x7E]+'
    };

    function detectPreset(val, presets) {
        for (var k in presets) {
            if (presets.hasOwnProperty(k) && presets[k] === val) return k;
        }
        return 'custom';
    }

    function initPreset(selId, inpId, presets) {
        var sel = document.getElementById(selId);
        var inp = document.getElementById(inpId);
        if (!sel || !inp) return;

        var current = detectPreset(inp.value, presets);
        sel.value    = current;
        inp.readOnly = (current !== 'custom');

        sel.addEventListener('change', function () {
            if (sel.value === 'custom') {
                inp.readOnly = false;
                inp.focus();
            } else {
                inp.value    = presets[sel.value];
                inp.readOnly = true;
            }
        });
    }

    initPreset('cfg-username-preset',    'cfg-username',    PRESETS_USER);
    initPreset('cfg-channelname-preset', 'cfg-channelname', PRESETS_CHAN);
})();
