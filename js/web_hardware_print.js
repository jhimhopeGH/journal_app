/**
 * web_hardware_print.js - Direct Hardware Thermal Printing via Web Serial / WebUSB
 * 
 * Sends pure ESC/POS binary data directly to the client's local thermal printer via USB / Serial.
 * Prints with 100% authentic Generic/Text Only native hardware character matrix ROM font.
 * No server firewall ports or network sockets required.
 */

(function(window) {
    'use strict';

    // Helper: Convert Base64 string to Uint8Array
    function base64ToUint8Array(base64) {
        const binaryString = window.atob(base64);
        const len = binaryString.length;
        const bytes = new Uint8Array(len);
        for (let i = 0; i < len; i++) {
            bytes[i] = binaryString.charCodeAt(i);
        }
        return bytes;
    }

    const HW_SETTINGS_KEY = 'hw_pos_print_settings';

    function getHwSettings() {
        try {
            const saved = localStorage.getItem(HW_SETTINGS_KEY);
            if (saved) return JSON.parse(saved);
        } catch (_) {}
        return {
            mode: 'serial',       // 'serial' or 'usb'
            baudRate: 9600,       // 9600, 19200, 38400, 115200
            rememberPort: true
        };
    }

    function saveHwSettings(settings) {
        try {
            localStorage.setItem(HW_SETTINGS_KEY, JSON.stringify(settings));
        } catch (_) {}
    }

    // Check system compatibility
    function checkCompatibility() {
        const isSecure = Boolean(window.isSecureContext);
        const hasSerial = Boolean('serial' in navigator && navigator.serial);
        const hasUsb = Boolean('usb' in navigator && navigator.usb);
        return {
            isSecure,
            hasSerial,
            hasUsb,
            supported: hasSerial || hasUsb
        };
    }

    /**
     * Send raw ESC/POS Uint8Array bytes over Web Serial
     */
    async function sendRawSerial(uint8Data, baudRate = 9600, forcePrompt = false) {
        if (!('serial' in navigator) || !navigator.serial) {
            throw new Error('Web Serial is disabled or not available in this browser context (Requires HTTPS, Localhost, or Insecure Origin flag).');
        }

        let port = null;
        const existingPorts = await navigator.serial.getPorts();

        if (!forcePrompt && existingPorts && existingPorts.length > 0) {
            port = existingPorts[0];
        } else {
            port = await navigator.serial.requestPort();
        }

        if (!port) {
            throw new Error('No serial port selected.');
        }

        let openedHere = false;
        if (!port.readable || !port.writable) {
            await port.open({ baudRate: parseInt(baudRate, 10) || 9600 });
            openedHere = true;
        }

        try {
            const writer = port.writable.getWriter();
            try {
                await writer.write(uint8Data);
            } finally {
                writer.releaseLock();
            }
        } finally {
            if (openedHere) {
                // Give the printer buffer 150ms to finish receiving before closing port
                await new Promise(r => setTimeout(r, 150));
                try {
                    await port.close();
                } catch (_) {}
            }
        }
    }

    /**
     * Send raw ESC/POS Uint8Array bytes over WebUSB
     */
    async function sendRawUsb(uint8Data, forcePrompt = false) {
        if (!('usb' in navigator) || !navigator.usb) {
            throw new Error('WebUSB is disabled or not available in this browser context (Requires HTTPS, Localhost, or Insecure Origin flag).');
        }

        let device = null;
        const existingDevices = await navigator.usb.getDevices();

        if (!forcePrompt && existingDevices && existingDevices.length > 0) {
            device = existingDevices[0];
        } else {
            device = await navigator.usb.requestDevice({ filters: [] });
        }

        if (!device) {
            throw new Error('No USB device selected.');
        }

        await device.open();
        try {
            if (device.configuration === null) {
                await device.selectConfiguration(1);
            }

            let outEndpoint = null;
            let interfaceNumber = 0;

            for (const iface of device.configuration.interfaces) {
                for (const alt of iface.alternates) {
                    for (const ep of alt.endpoints) {
                        if (ep.direction === 'out') {
                            outEndpoint = ep.endpointNumber;
                            interfaceNumber = iface.interfaceNumber;
                            break;
                        }
                    }
                    if (outEndpoint !== null) break;
                }
                if (outEndpoint !== null) break;
            }

            if (outEndpoint === null) {
                throw new Error('Could not find an OUT transfer endpoint on this USB printer.');
            }

            await device.claimInterface(interfaceNumber);
            try {
                const chunkSize = 512;
                for (let i = 0; i < uint8Data.length; i += chunkSize) {
                    const chunk = uint8Data.slice(i, i + chunkSize);
                    await device.transferOut(outEndpoint, chunk);
                }
            } finally {
                try { await device.releaseInterface(interfaceNumber); } catch (_) {}
            }
        } finally {
            try { await device.close(); } catch (_) {}
        }
    }

    /**
     * Fetch raw base64 payload from backend and transmit to hardware
     */
    async function executeHardwarePrint(fetchUrl, fetchBody = null, forcePrompt = false) {
        const settings = getHwSettings();
        const compat = checkCompatibility();

        if (!compat.supported) {
            openHardwareSetupModal({
                errorMsg: !compat.isSecure
                    ? 'Browser security requires HTTPS or Localhost for hardware USB/Serial access. See instructions below.'
                    : 'Web Serial / WebUSB API is only supported in Google Chrome, Microsoft Edge, or Chromium-based browsers.'
            });
            return { success: false, error: 'Compatibility issue' };
        }

        // Fetch ESC/POS payload from backend
        let respData;
        try {
            const opts = fetchBody 
                ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(fetchBody) }
                : { method: 'GET' };
            const resp = await fetch(fetchUrl, opts);
            respData = await resp.json();
        } catch (err) {
            throw new Error('Failed to retrieve receipt raw payload: ' + err.message);
        }

        if (!respData.success || !respData.base64) {
            throw new Error(respData.error || 'Server did not return raw receipt payload.');
        }

        const rawBytes = base64ToUint8Array(respData.base64);

        if (settings.mode === 'usb') {
            await sendRawUsb(rawBytes, forcePrompt);
        } else {
            await sendRawSerial(rawBytes, settings.baudRate, forcePrompt);
        }

        return { success: true, count: respData.count || 1, bytes: rawBytes.length };
    }

    /**
     * Public API: Print a single receipt entry by ID
     */
    async function printReceiptHardware(entryId, forcePrompt = false) {
        return executeHardwarePrint('receipt_raw_payload_ajax.php?id=' + encodeURIComponent(entryId), null, forcePrompt);
    }

    /**
     * Public API: Print a test receipt to verify hardware Generic/Text ROM font
     */
    async function printTestReceiptHardware(forcePrompt = false) {
        return executeHardwarePrint('receipt_raw_payload_ajax.php?test=1', null, forcePrompt);
    }

    /**
     * Public API: Print multiple receipt entries by array of IDs
     */
    async function printBatchHardware(entryIds, forcePrompt = false) {
        const idStr = Array.isArray(entryIds) ? entryIds.join(',') : entryIds;
        return executeHardwarePrint('receipt_raw_payload_ajax.php?ids=' + encodeURIComponent(idStr), null, forcePrompt);
    }

    /**
     * Renders or displays the Hardware Setup / Printer Pairing Modal
     */
    function openHardwareSetupModal(options = {}) {
        let modal = document.getElementById('hw_print_setup_modal');
        if (!modal) {
            modal = createHardwareModalElement();
            document.body.appendChild(modal);
        }

        const settings = getHwSettings();
        const compat = checkCompatibility();

        const modeSelect = document.getElementById('hw_mode_select');
        const baudSelect = document.getElementById('hw_baud_select');
        const alertBox   = document.getElementById('hw_alert_box');

        if (modeSelect) modeSelect.value = settings.mode;
        if (baudSelect) baudSelect.value = settings.baudRate;

        if (options.errorMsg) {
            alertBox.style.display = 'block';
            alertBox.innerHTML = options.errorMsg;
        } else if (!compat.isSecure) {
            const currentOrigin = window.location.origin;
            alertBox.style.display = 'block';
            alertBox.innerHTML = `
                <strong>⚠️ Non-Secure Origin Detected (${window.location.hostname})</strong><br>
                Chrome/Edge requires a secure context to talk directly to USB/COM hardware.<br>
                <div style="margin-top:6px; font-size:12px; line-height:1.5;">
                    <strong>To enable in 30 seconds on Cashier PC:</strong><br>
                    1. Open a new tab and go to: <code style="background:#fee2e2;padding:2px 4px;border-radius:3px;">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code><br>
                    2. Add <code style="background:#fee2e2;padding:2px 4px;border-radius:3px;">${currentOrigin}</code> to the box.<br>
                    3. Change dropdown to <strong>Enabled</strong> and click <strong>Relaunch</strong>.
                </div>
            `;
        } else {
            alertBox.style.display = 'none';
        }

        modal.classList.add('active');
    }

    function closeHardwareSetupModal() {
        const modal = document.getElementById('hw_print_setup_modal');
        if (modal) modal.classList.remove('active');
    }

    function createHardwareModalElement() {
        const wrapper = document.createElement('div');
        wrapper.id = 'hw_print_setup_modal';
        wrapper.className = 'modal-overlay';
        wrapper.innerHTML = `
            <div class="modal-box" style="max-width:540px;">
                <div class="modal-header" style="background:#1e1b4b;color:#fff;padding:14px 20px;border-radius:10px 10px 0 0;display:flex;justify-content:space-between;align-items:center;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <span style="font-size:20px;">⚡</span>
                        <div>
                            <h3 style="margin:0;font-size:16px;font-weight:700;">Hardware Direct Printing Setup</h3>
                            <div style="font-size:12px;opacity:0.8;">100% Native Generic/Text ROM Font via USB/Serial</div>
                        </div>
                    </div>
                    <button type="button" class="modal-close" style="color:#fff;background:none;border:none;font-size:22px;cursor:pointer;" onclick="window.WebHardwarePrint.closeModal()">&times;</button>
                </div>
                <div class="modal-body" style="padding:20px;">
                    <div id="hw_alert_box" style="display:none;background:#fef2f2;border-left:4px solid #ef4444;color:#991b1b;padding:12px;border-radius:4px;margin-bottom:16px;font-size:13px;"></div>

                    <p style="margin-top:0;font-size:13px;color:#475569;line-height:1.6;">
                        This feature sends raw ESC/POS commands directly down your USB / COM cable. The receipt is rendered by the physical printer microchip, matching the server's Generic/Text Only printout exactly.
                    </p>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-bottom:16px;">
                        <div style="margin-bottom:12px;">
                            <label style="display:block;font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">Connection Interface:</label>
                            <select id="hw_mode_select" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff;">
                                <option value="serial">🔌 Web Serial (USB Virtual COM / Serial Port) — Recommended</option>
                                <option value="usb">🖲️ WebUSB (Direct Raw USB Device)</option>
                            </select>
                            <span style="font-size:11px;color:#64748b;margin-top:4px;display:block;">
                                Most thermal printers (Epson, Xprinter, POS-58/80) use USB Virtual COM (e.g. COM3, COM4).
                            </span>
                        </div>

                        <div>
                            <label style="display:block;font-size:13px;font-weight:600;color:#1e293b;margin-bottom:4px;">Baud Rate (for Serial/COM):</label>
                            <select id="hw_baud_select" style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;font-size:13px;background:#fff;">
                                <option value="9600">9600 bps (Standard POS Default)</option>
                                <option value="19200">19200 bps</option>
                                <option value="38400">38400 bps</option>
                                <option value="115200">115200 bps (High Speed USB-Serial)</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" class="btn" id="hw_btn_save_settings" style="background:#0284c7;color:#fff;padding:8px 16px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">
                            Save Settings
                        </button>
                        <button type="button" class="btn" id="hw_btn_pair_port" style="background:#7c3aed;color:#fff;padding:8px 18px;border:none;border-radius:6px;font-weight:600;cursor:pointer;">
                            🔍 Select / Pair Printer
                        </button>
                    </div>
                </div>
            </div>
        `;

        wrapper.addEventListener('click', (e) => {
            if (e.target === wrapper) closeHardwareSetupModal();
        });

        // Wire event handlers inside modal
        setTimeout(() => {
            const saveBtn = wrapper.querySelector('#hw_btn_save_settings');
            const pairBtn = wrapper.querySelector('#hw_btn_pair_port');
            const modeSelect = wrapper.querySelector('#hw_mode_select');
            const baudSelect = wrapper.querySelector('#hw_baud_select');

            saveBtn?.addEventListener('click', () => {
                const s = getHwSettings();
                s.mode = modeSelect.value;
                s.baudRate = parseInt(baudSelect.value, 10);
                saveHwSettings(s);
                closeHardwareSetupModal();
                if (typeof window.showToast === 'function') {
                    window.showToast('Hardware printer settings saved!');
                }
            });

            pairBtn?.addEventListener('click', async () => {
                const mode = modeSelect.value;
                const baud = parseInt(baudSelect.value, 10);
                const s = getHwSettings();
                s.mode = mode;
                s.baudRate = baud;
                saveHwSettings(s);

                const currentOrigin = window.location.origin;

                try {
                    if (mode === 'usb') {
                        if (!('usb' in navigator) || !navigator.usb) {
                            throw new Error('WebUSB is disabled on HTTP origins (' + window.location.hostname + '). Please enable the insecure origin flag in Chrome/Edge.');
                        }
                        await navigator.usb.requestDevice({ filters: [] });
                    } else {
                        if (!('serial' in navigator) || !navigator.serial) {
                            throw new Error('WebSerial is disabled on HTTP LAN origins (' + window.location.hostname + '). Please enable the insecure origin flag in Chrome/Edge.');
                        }
                        await navigator.serial.requestPort();
                    }
                    closeHardwareSetupModal();
                    if (typeof window.showToast === 'function') {
                        window.showToast('Printer paired successfully! Ready to print.');
                    }
                } catch (err) {
                    if (err.name !== 'NotFoundError') {
                        if (alertBox) {
                            alertBox.style.display = 'block';
                            alertBox.innerHTML = `
                                <strong>⚠️ WebSerial Disabled on HTTP (${window.location.hostname})</strong><br>
                                Chrome and Edge block direct USB/COM hardware on plain HTTP IP addresses by default.<br>
                                <div style="margin-top:8px; font-size:12.5px; line-height:1.6; background:#fff; padding:10px; border-radius:6px; border:1px solid #fca5a5;">
                                    <strong>How to enable on Cashier PC (One-time, 15 seconds):</strong><br>
                                    1. Open a new browser tab and go to:<br>
                                    <code style="background:#f1f5f9;color:#0f172a;padding:2px 6px;border-radius:4px;font-weight:700;display:inline-block;margin:3px 0;">chrome://flags/#unsafely-treat-insecure-origin-as-secure</code><br>
                                    <em>(or <code>edge://flags/#unsafely-treat-insecure-origin-as-secure</code> if using Edge)</em><br>
                                    2. In the text box, type: <strong style="color:#7c3aed;">${currentOrigin}</strong><br>
                                    3. Change dropdown to <strong>Enabled</strong> and click <strong>Relaunch</strong>.<br>
                                    4. Refresh this page and click <strong>Select / Pair Printer</strong> again! ✓
                                </div>
                            `;
                        } else {
                            alert('Selection error: ' + err.message);
                        }
                    }
                }
            });
        }, 50);

        return wrapper;
    }

    // Export module
    window.WebHardwarePrint = {
        isSupported: () => checkCompatibility().supported,
        checkCompatibility: checkCompatibility,
        getSettings: getHwSettings,
        saveSettings: saveHwSettings,
        printReceipt: printReceiptHardware,
        printTestReceipt: printTestReceiptHardware,
        printBatch: printBatchHardware,
        openModal: openHardwareSetupModal,
        closeModal: closeHardwareSetupModal
    };

})(window);
