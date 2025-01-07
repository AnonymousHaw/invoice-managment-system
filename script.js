document.addEventListener('DOMContentLoaded', function() {
    // Add invoice item row
    const addItemButton = document.getElementById('add-item');
    if (addItemButton) {
        addItemButton.addEventListener('click', function() {
            const itemsContainer = document.getElementById('invoice-items');
            const newRow = document.createElement('div');
            newRow.classList.add('item-row');
            newRow.innerHTML = `
                <input type="text" name="description[]" placeholder="Description" required>
                <input type="number" name="quantity[]" placeholder="Quantity" required>
                <input type="number" name="unit_price[]" placeholder="Unit Price" step="0.01" required>
                <span class="total">0.00</span>
                <button type="button" class="remove-item">Remove</button>
            `;
            itemsContainer.appendChild(newRow);
        });
    }

    // Calculate totals
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[name="quantity[]"]') || e.target.matches('input[name="unit_price[]"]')) {
            calculateTotals();
        }
    });

    // Remove item row
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-item')) {
            e.target.closest('.item-row').remove();
            calculateTotals();
        }
    });
});

function calculateTotals() {
    const rows = document.querySelectorAll('.item-row');
    let subtotal = 0;

    rows.forEach(row => {
        const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('input[name="unit_price[]"]').value) || 0;
        const total = quantity * unitPrice;
        row.querySelector('.total').textContent = total.toFixed(2);
        subtotal += total;
    });

    const tax = subtotal * 0.1; // 10% tax
    const total = subtotal + tax;

    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('tax').textContent = tax.toFixed(2);
    document.getElementById('total').textContent = total.toFixed(2);
}