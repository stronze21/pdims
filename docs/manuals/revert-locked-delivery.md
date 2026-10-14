# Reverting a locked delivery

For delivery staff with **view-deliveries** access at the delivery's pharmacy location.

1. Open Deliveries and view the locked delivery.
2. Select **Revert Delivery** to open the **Revert Locked Delivery** modal. Review the invoice reference and inventory warning, then enter a reason of 5–1000 characters.
3. Check the confirmation that all delivered quantities should be removed, then select **Confirm Reversal**. The button remains disabled until you confirm. Select **Cancel** to leave inventory unchanged. Validation and insufficient-balance errors stay in the modal for review.
4. The delivery returns to **Pending** only when all original batches have sufficient balance. You may then correct the pending items and use **Save & Lock** again.
5. Expand **Delivery audit trail** to review the user, date/time, reason, quantities, lots and before/after balances. Locking and re-locking also retain an audit entry.

Reversal subtracts from the original purchase receipt and stock-card receipt. It does not record an issue, pull-out or consumption. A failed reversal leaves every item unchanged. Missing or ambiguous original records, changed batches, finalized consumption periods and receipts from an earlier reporting month without a consumption period require manual reconciliation by the inventory administrator.

Historical locked deliveries can be reversed only when their original stock, purchase log and receipt card can each be identified uniquely. Earlier lock events are not retroactively fabricated; the reversal records the available original posting references.
