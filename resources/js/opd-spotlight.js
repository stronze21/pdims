const guidance = {
    'rx-entry-start': ['+ Rx', 'Open a new prescription for the sample OPD patient.'],
    'rx-entry-search': ['Search for the medicine', 'Type Sample Medication A to find the training item.'],
    'rx-entry-add': ['Add', 'Add the sample medicine from the search results to the prescription form.'],
    'rx-entry-quantity': ['Enter the quantity', 'Enter 1 for this training prescription.'],
    'rx-entry-frequency': ['Choose the frequency', 'Select Once daily for the sample item.'],
    'rx-entry-days': ['Enter the number of days', 'Enter 1 for this exercise.'],
    'rx-entry-remarks': ['Enter remarks', 'Enter the sample remarks.'],
    'rx-entry-queue': ['Proceed to Pharmacy?', 'Check this option to send the prescription to the pharmacy queue when you submit it.'],
    'rx-entry-submit': ['Submit', 'Save the sample prescription and send it to Pharmacy.'],
    'rx-entry-care': ['No, thanks', 'Skip the optional care order. The sample prescription is already queued for Pharmacy.'],
    'rx-entry-handoff': ['Continue to PDIMS', 'Follow RX-001 into the pharmacy queue and call it from the Queue Controller.'],
    'call-queue': ['Call Next Queue', 'Bring the waiting prescription into Window 1 and see how it appears on the pharmacy TV.'],
    'tv-waiting': ['Waiting', 'Patients see RX-001 in the Waiting column before the pharmacy calls the queue.'],
    'tv-preparing': ['Now Preparing', 'RX-001 has moved to Window 1. The smaller Waiting column shows numbers that haven’t been called.'],
    'tv-ready': ['Ready for Claiming', 'Charging moves RX-001 to Ready for Claiming. It shows Window 1 before staff call for the patient.'],
    'tv-calling': ['Now Calling', 'RX-001 is ready to claim. The Waiting column still shows the uncalled numbers.'],
    'tv-dispensed': ['After Dispensing', 'RX-001 disappears from the patient display once the medicine is issued. Other waiting numbers stay on screen.'],
    'open-dispensing': ['Open Dispensing', 'Check that the patient and queue number match before opening the dispensing encounter.'],
    'select-pending': ['Select the pending item', 'Check the box beside Sample Medication A to prepare it for charging.'],
    charge: ['Charge', 'Charge the selected sample medication. The queue will move to Ready.'],
    'call-patient': ['Call for Patient', 'Announce that RX-001 is ready for claiming on the pharmacy TV.'],
    'confirm-call': ['Confirm the call', 'Confirm to see the patient-facing Now Calling alert.'],
    'select-charged': ['Select the charged item', 'Check the charged medication before issuing it to the patient.'],
    issue: ['Issue', 'Open the issue confirmation to review the transaction details.'],
    department: ['Choose the prescribing clinic', 'Select the OPD clinic that prescribed the sample medication.'],
    'confirm-issue': ['Confirm Issue', 'Review the OPD clinic prefilled from the prescription, then confirm the handover. The queue will move to Dispensed.'],
    'processing-done': ['Done', 'Close the transaction result to continue the walkthrough.'],
    tools: ['Open Tools', 'The prescription printing action is in the dispensing Tools menu.'],
    'print-rx': ['Print Rx', 'Open the prescription and choose which items to include on the form.'],
    'select-print-item': ['Select the prescription item', 'Check Sample Medication A to include it in the prescription preview.'],
    'print-selected': ['Print Selected Items', 'Open the training preview. Clear the deduction option to show the full quantity, or leave it checked to show the remaining balance.'],
};

function available(element) {
    return element && !element.disabled && element.getClientRects().length
        && getComputedStyle(element).visibility === 'visible';
}

function nextAction(root) {
    const action = name => root.querySelector(`[data-opd-action="${name}"]`);
    const processingDone = action('processing-done');
    const department = action('department');
    const printItem = action('select-print-item');
    const continueTv = action('continue-tv');
    const banner = root.querySelector('[data-opd-stage]');

    if (banner?.dataset.opdStage.startsWith('rx-entry-')) {
        if (root.querySelector('dialog[data-training-navigation][open]')) return [];
        return [action(banner.dataset.opdStage), banner.dataset.opdStage];
    }

    if (continueTv?.closest('dialog').open) {
        const phase = banner.dataset.opdStage === 'ready' && banner.dataset.opdPatientCalled === 'yes'
            ? 'calling'
            : banner.dataset.opdStage;
        return [continueTv, `tv-${phase}`];
    }
    if (processingDone?.closest('dialog').open) {
        return [processingDone, 'processing-done'];
    }
    if (department?.closest('dialog').open) {
        return department.value
            ? [action('confirm-issue'), 'confirm-issue']
            : [department, 'department'];
    }
    if (printItem?.closest('dialog').open) {
        const printButton = action('print-selected');
        return available(printButton) ? [printButton, 'print-selected'] : [printItem, 'select-print-item'];
    }

    switch (banner?.dataset.opdStage) {
        case 'waiting':
            return [action('call-queue'), 'call-queue'];
        case 'preparing':
            if (action('open-dispensing')) return [action('open-dispensing'), 'open-dispensing'];
            return available(action('charge')) ? [action('charge'), 'charge'] : [action('select-order'), 'select-pending'];
        case 'ready':
            {
                const confirmCall = document.querySelector('.modal-open [data-opd-action="confirm-call"]');
                if (confirmCall) return [confirmCall, 'confirm-call'];
                if (banner.dataset.opdPatientCalled === 'no') return [action('call-patient'), 'call-patient'];
            }
            return available(action('issue')) ? [action('issue'), 'issue'] : [action('select-order'), 'select-charged'];
        case 'dispensed':
            return available(action('print-rx')) ? [action('print-rx'), 'print-rx'] : [action('tools'), 'tools'];
        default:
            return [];
    }
}

function startSpotlight() {
    const root = document.querySelector('[data-opd-stage]')?.parentElement;
    if (!root || !HTMLElement.prototype.showPopover) return;

    const overlay = document.createElement('div');
    overlay.className = 'opd-spotlight';
    overlay.popover = 'manual';
    overlay.innerHTML = `
        <div class="opd-spotlight-ring" aria-hidden="true"><div class="opd-spotlight-outline"></div></div>
        <div id="opd-spotlight-message" class="opd-spotlight-bubble" role="status">
            <strong></strong><p></p>
        </div>
    `;
    document.body.append(overlay);

    const ring = overlay.firstElementChild;
    const outline = ring.firstElementChild;
    const bubble = overlay.lastElementChild;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let bubbleAnimation;
    let outlineAnimation;
    let travelAnimation;
    let previous;
    let previousStep;
    let previousDialog;
    let previousDescription;
    let pendingOrigin;
    let frame;

    function render() {
        frame = null;
        const [target, step] = nextAction(root);
        const rect = available(target) ? target.getBoundingClientRect() : null;
        const width = document.documentElement.clientWidth;
        const height = document.documentElement.clientHeight;
        const visible = rect && rect.right > 0 && rect.left < width && rect.bottom > 0 && rect.top < height;
        const dialog = visible ? target.closest('dialog[open], .modal-open') : null;
        const open = overlay.matches(':popover-open');
        const sameLayer = open && dialog === previousDialog;
        const targetChanged = target !== previous || dialog !== previousDialog;
        const origin = visible && targetChanged && !reducedMotion.matches
            ? (open ? ring.getBoundingClientRect() : pendingOrigin)
            : null;

        if (target !== previous || !visible) {
            if (previous && previousDescription === null) previous.removeAttribute('aria-describedby');
            else if (previous) previous.setAttribute('aria-describedby', previousDescription);
            previous = undefined;
        }
        if (!visible) {
            if (open) pendingOrigin = ring.getBoundingClientRect();
            travelAnimation?.cancel();
            travelAnimation = undefined;
            outlineAnimation?.cancel();
            outlineAnimation = undefined;
            if (open) overlay.hidePopover();
            return;
        }
        pendingOrigin = null;
        if (!previous) {
            previous = target;
            previousDescription = target.getAttribute('aria-describedby');
        }
        if (!target.getAttribute('aria-describedby')?.split(/\s+/).includes(bubble.id)) {
            target.setAttribute('aria-describedby', [previousDescription, bubble.id].filter(Boolean).join(' '));
        }
        const changed = step !== previousStep;
        if (changed) {
            const [title, description] = guidance[step];
            bubble.firstElementChild.textContent = target.dataset.opdTitle ?? title;
            bubble.lastElementChild.textContent = target.dataset.opdDescription ?? description;
            previousStep = step;
        }
        if (open && dialog !== previousDialog) overlay.hidePopover();
        if (!overlay.matches(':popover-open')) overlay.showPopover();
        const tv = dialog?.classList.contains('opd-training-tv') ?? false;
        if (!dialog && previousDialog?.classList.contains('opd-training-tv')) target.focus({ preventScroll: true });
        overlay.toggleAttribute('data-opd-tv', tv);
        previousDialog = dialog;

        const inset = 5;
        const left = Math.max(2, rect.left - inset);
        const top = Math.max(2, rect.top - inset);
        const right = Math.min(width - 2, rect.right + inset);
        const bottom = Math.min(height - 2, rect.bottom + inset);
        const destination = { left: `${left}px`, top: `${top}px`, width: `${right - left}px`, height: `${bottom - top}px` };
        const geometryChanged = Object.entries(destination).some(([property, value]) => ring.style[property] !== value);
        if (geometryChanged || !sameLayer || reducedMotion.matches) {
            travelAnimation?.cancel();
            travelAnimation = undefined;
            Object.assign(ring.style, destination);
            if (origin && geometryChanged) {
                travelAnimation = ring.animate([
                    { left: `${origin.left}px`, top: `${origin.top}px`, width: `${origin.width}px`, height: `${origin.height}px` },
                    destination,
                ], { duration: 320, easing: 'cubic-bezier(0.25, 0.8, 0.25, 1)' });
            }
        }
        if (reducedMotion.matches) {
            outlineAnimation?.cancel();
            outlineAnimation = undefined;
        } else if (!outlineAnimation) {
            outlineAnimation = outline.animate(
                [
                    { inset: '0px', borderRadius: '10px', boxShadow: '0 0 2px color-mix(in srgb, var(--color-primary) 12%, transparent)' },
                    { inset: '-8px', borderRadius: '18px', boxShadow: '0 0 14px color-mix(in srgb, var(--color-primary) 45%, transparent)' },
                ],
                { duration: 1500, iterations: Infinity, direction: 'alternate', easing: 'ease-in-out' },
            );
        }

        const margin = 12;
        const gap = 12;
        const beside = tv && left >= bubble.offsetWidth + margin + gap;
        const above = bottom + gap + bubble.offsetHeight > height - margin && top > height - bottom;
        const bubbleLeft = beside ? left - gap - bubble.offsetWidth
            : Math.min(Math.max(margin, left), width - bubble.offsetWidth - margin);
        const targetCenter = (top + bottom) / 2;
        const desiredTop = beside ? targetCenter - bubble.offsetHeight / 2
            : (above ? top - gap - bubble.offsetHeight : bottom + gap);
        const bubbleTop = Math.max(margin, Math.min(desiredTop, height - bubble.offsetHeight - margin));
        bubble.dataset.placement = beside ? 'beside' : (above ? 'above' : 'below');
        bubble.style.left = `${bubbleLeft}px`;
        bubble.style.top = `${bubbleTop}px`;
        const tip = beside ? Math.min(bubble.offsetHeight - 16, Math.max(16, targetCenter - bubbleTop))
            : Math.min(bubble.offsetWidth - 16, Math.max(16, (left + right) / 2 - bubbleLeft));
        bubble.style.setProperty('--opd-tip', `${tip}px`);
        if (changed) {
            bubbleAnimation?.cancel();
            bubbleAnimation = origin ? undefined : bubble.animate(
                [
                    { opacity: .35, translate: reducedMotion.matches ? 'none' : (beside ? '6px 0' : `0 ${above ? 6 : -6}px`) },
                    { opacity: 1, translate: 'none' },
                ],
                { duration: reducedMotion.matches ? 90 : 180, easing: 'ease-out' },
            );
        }
    }

    function schedule() {
        if (frame == null) frame = requestAnimationFrame(render);
    }

    new MutationObserver(schedule).observe(root, {
        subtree: true,
        childList: true,
        attributes: true,
        attributeFilter: ['class', 'disabled', 'data-opd-stage', 'data-opd-patient-called', 'open', 'style', 'checked'],
    });
    for (const event of ['click', 'input', 'change', 'focusin', 'focusout', 'scroll', 'transitionend']) {
        document.addEventListener(event, schedule, { capture: true, passive: true });
    }
    window.addEventListener('resize', schedule);
    reducedMotion.addEventListener('change', schedule);
    schedule();
}

document.addEventListener('DOMContentLoaded', startSpotlight);
