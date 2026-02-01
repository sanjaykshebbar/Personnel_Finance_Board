# Manual Testing Guide - Credit Card Save Fix

## ✅ Fix Applied

**File:** `pages/credit.php`

**Change:** Moved POST handler BEFORE `header.php` include to prevent "headers already sent" error.

**Before:**
```php
require_once '../includes/header.php';  // ❌ Outputs HTML first
// ... then POST handler tries to redirect
header("Location: credit.php");  // ❌ ERROR: headers already sent
```

**After:**
```php
// ✅ POST handler runs FIRST (no output yet)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... save credit card
    header("Location: credit.php");  // ✅ Works! No output yet
    exit;
}
// ✅ NOW load header (outputs HTML)
require_once '../includes/header.php';
```

---

## 🧪 Manual Testing Steps

### Test 1: Add New Credit Card

1. **Open browser** and go to: `http://localhost:8000/pages/credit.php`

2. **Fill in the form:**
   - Provider / Card Name: `HDFC Credit Card`
   - Credit Limit: `50000`
   - Previous Balance: (auto-calculated, read-only - should show ₹0.00 for new card)

3. **Click "Save Card"**

4. **Expected Results:**
   - ✅ Page redirects successfully (no error message)
   - ✅ Flash message appears: "Credit account added!"
   - ✅ New card appears in the grid below
   - ✅ Card shows:
     - Name: HDFC Credit Card
     - Limit: ₹50,000
     - Utilization: 0%
     - Previous Balance: ₹0

---

### Test 2: Add Expense with New Card

1. **Go to Expenses page:** `http://localhost:8000/pages/expenses.php`

2. **Check payment method dropdown:**
   - ✅ "HDFC Credit Card" should appear in the list
   - ✅ Should also see "Bank Account" and "Cash" (defaults)

3. **Add an expense:**
   - Date: Today
   - Category: Shopping
   - Description: "Test Purchase"
   - Amount: `5000`
   - Payment Method: **HDFC Credit Card**
   - Click "Add Expense"

4. **Expected Results:**
   - ✅ Expense saved successfully
   - ✅ Flash message: "Expense added."

---

### Test 3: Verify Auto-Calculated Balance

1. **Go back to Credit Usage:** `http://localhost:8000/pages/credit.php`

2. **Click Edit (✏️) on "HDFC Credit Card"**

3. **Check the form:**
   - ✅ Previous Balance field should show: `5000.00`
   - ✅ Field should be read-only (gray background)
   - ✅ Label says: "Previous Balance (Auto-Calculated) · From past expenses"

4. **Check the card display:**
   - ✅ Spent: ₹5,000
   - ✅ Available: ₹45,000
   - ✅ Utilization: 10%

---

### Test 4: Add Another Credit Card

1. **Add second card:**
   - Provider: `Axis Bank Credit Card`
   - Limit: `100000`
   - Click "Save Card"

2. **Expected Results:**
   - ✅ Both cards visible in grid
   - ✅ Go to Expenses page
   - ✅ Both cards in payment method dropdown

---

### Test 5: Update Existing Card

1. **Edit "HDFC Credit Card"**
2. **Change Credit Limit to:** `75000`
3. **Click "Update Account"**

4. **Expected Results:**
   - ✅ Flash message: "Account details updated."
   - ✅ Card shows new limit: ₹75,000
   - ✅ Utilization recalculated: ~6.7%

---

### Test 6: Delete Card

1. **Click delete (🗑️) on "Axis Bank Credit Card"**
2. **Confirm deletion**

3. **Expected Results:**
   - ✅ Flash message: "Credit account deleted."
   - ✅ Card removed from grid
   - ✅ Go to Expenses page
   - ✅ "Axis Bank Credit Card" no longer in dropdown

---

## ❌ If You See Errors

### Error: "headers already sent"
**Cause:** The fix didn't apply correctly  
**Solution:** Refresh the page with Ctrl+Shift+R to clear cache

### Error: "Credit account added!" but card doesn't appear
**Cause:** Database issue  
**Solution:** Check server terminal for SQL errors

### Error: Card appears but not in Expenses dropdown
**Cause:** Dynamic loading not working  
**Solution:** Check that `expenses.php` has the dynamic payment method code

---

## 📊 Expected Final State

After all tests, you should have:

**Credit Usage Page:**
- 1 credit card: "HDFC Credit Card"
- Limit: ₹75,000
- Previous Balance: ₹5,000
- Utilization: ~6.7%

**Expenses Page:**
- Payment methods dropdown includes:
  - Bank Account
  - Cash
  - HDFC Credit Card

**Recent Expenses:**
- 1 expense: "Test Purchase" for ₹5,000 via HDFC Credit Card

---

## ✅ Success Criteria

- [ ] Can add new credit card without errors
- [ ] Card appears in Credit Usage grid
- [ ] Card appears in Expenses payment method dropdown
- [ ] Previous balance auto-calculates from expenses
- [ ] Can update credit card details
- [ ] Can delete credit card
- [ ] No "headers already sent" errors

---

**Ready to test!** Follow the steps above and let me know if you encounter any issues.
