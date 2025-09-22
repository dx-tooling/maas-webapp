import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["container", "template", "saveButton"];
    static values = {
        initialData: Object,
    };

    connect() {
        this.initialState = this.captureCurrentState();
        this.updateSaveButtonState();
    }

    add() {
        const template = this.templateTarget.content.cloneNode(true);
        const newRow = template.querySelector(".env-var-row");
        this.containerTarget.appendChild(template);

        // Add event listeners to the new row (query BEFORE append; fragment gets emptied on append)
        if (newRow) {
            this.addEventListenersToRow(newRow);
        }
        this.updateSaveButtonState();
    }

    remove(event) {
        event.target.closest(".env-var-row").remove();
        this.updateSaveButtonState();
    }

    inputChanged(event) {
        // Sanitize key input to match server rules: uppercase letters, numbers, underscores; start with letter or underscore
        const target = event && event.target ? event.target : null;
        if (target && target.name === "env_keys[]" && target instanceof HTMLInputElement) {
            const original = target.value;
            let sanitized = original.toUpperCase().replace(/[^A-Z0-9_]/g, "_");
            if (sanitized !== "" && /[^A-Z_]/.test(sanitized.charAt(0))) {
                sanitized = "_" + sanitized;
            }
            if (sanitized !== original) {
                const selectionStart = target.selectionStart;
                const selectionEnd = target.selectionEnd;
                target.value = sanitized;
                // try to preserve cursor position
                if (selectionStart !== null && selectionEnd !== null) {
                    const delta = sanitized.length - original.length;
                    const newStart = Math.max(0, selectionStart + delta);
                    const newEnd = Math.max(0, selectionEnd + delta);
                    target.setSelectionRange(newStart, newEnd);
                }
            }
        }
        this.updateSaveButtonState();
    }

    captureCurrentState() {
        const rows = this.containerTarget.querySelectorAll(".env-var-row");
        const state = [];

        rows.forEach((row) => {
            const keyInput = row.querySelector('input[name="env_keys[]"]');
            const valueInput = row.querySelector('input[name="env_values[]"]');

            if (keyInput && valueInput) {
                state.push({
                    key: keyInput.value.trim(),
                    value: valueInput.value.trim(),
                });
            }
        });

        return state;
    }

    hasChanges() {
        const currentState = this.captureCurrentState();

        // Compare with initial state
        if (currentState.length !== this.initialState.length) {
            return true;
        }

        // Check if any key-value pairs have changed
        for (let i = 0; i < currentState.length; i++) {
            const current = currentState[i];
            const initial = this.initialState[i];

            if (!initial || current.key !== initial.key || current.value !== initial.value) {
                return true;
            }
        }

        return false;
    }

    updateSaveButtonState() {
        const hasChanges = this.hasChanges();
        const inputsAreValid = this.inputsAreValid();
        const saveButton = this.saveButtonTarget;

        if (hasChanges && inputsAreValid) {
            saveButton.disabled = false;
            saveButton.classList.remove("etfswui-button-default-disabled");
            saveButton.classList.add("etfswui-button-default");
        } else {
            saveButton.disabled = true;
            saveButton.classList.remove("etfswui-button-default");
            saveButton.classList.add("etfswui-button-default-disabled");
        }
    }

    inputsAreValid() {
        const rows = this.containerTarget.querySelectorAll(".env-var-row");
        const keyPattern = /^[A-Z_][A-Z0-9_]*$/;
        for (const row of rows) {
            const keyInput = row.querySelector('input[name="env_keys[]"]');
            if (keyInput && keyInput instanceof HTMLInputElement) {
                const k = keyInput.value.trim();
                if (k !== "" && !keyPattern.test(k)) {
                    return false;
                }
            }
        }
        return true;
    }

    addEventListenersToRow(row) {
        const keyInput = row.querySelector('input[name="env_keys[]"]');
        const valueInput = row.querySelector('input[name="env_values[]"]');

        if (keyInput) {
            keyInput.addEventListener("input", () => this.inputChanged());
        }

        if (valueInput) {
            valueInput.addEventListener("input", () => this.inputChanged());
        }
    }
}
