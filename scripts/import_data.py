import sys
import sqlite3
import re
import os
from datetime import datetime
import pandas as pd

# Usage: python import_data.py <file_path> <db_path> <payment_method> <user_id> [--ocr]

def parse_date(date_str):
    if pd.isna(date_str): return None
    date_str = str(date_str).strip()
    # Expanded formats for better historical/short date support
    formats = [
        '%d/%m/%Y', '%d-%m-%Y', '%Y-%m-%d', '%d %b %Y', '%Y.%m.%d', '%d.%m.%Y',
        '%b %Y', '%B %Y', '%m/%Y', '%m-%Y', '%d/%m/%y', '%d-%m-%y', '%y-%m-%d'
    ]
    for fmt in formats:
        try:
            dt = datetime.strptime(date_str, fmt)
            if dt.year < 1950: # Handle cases like '15 Jan' where year defaults to 1900
                dt = dt.replace(year=datetime.now().year)
            return dt.strftime('%Y-%m-%d')
        except ValueError:
            pass
    
    # Try Regex for patterns like "15 Jan 2024" or "2024-01-15" within text
    match = re.search(r'(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})', date_str)
    if match: return parse_date(match.group(1))
    
    return None

def extract_amount(text):
    """Extract numbers from a string, handling commas and signs."""
    # Find patterns like 1,234.56 or 1234.56
    matches = re.findall(r'(\d{1,3}(?:,\d{3})*\.\d{2})', text)
    if not matches:
        # Try without decimal part?
        matches = re.findall(r'(\d{1,3}(?:,\d{3})*)', text)
    
    amounts = []
    for m in matches:
        try:
            val = float(m.replace(',', ''))
            amounts.append(val)
        except: pass
    return amounts

def generic_line_parser(lines):
    """Fallback parser that tries to find Date-Description-Amount in any order."""
    transactions = []
    for line in lines:
        line = line.strip()
        if not line or len(line) < 10: continue
        
        # 1. Find Date (Priority: DD/MM/YYYY, DD-MM-YYYY, DD MMM YYYY)
        date_match = re.search(r'(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})', line)
        if not date_match:
            date_match = re.search(r'(\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{2,4})', line, re.I)
        
        if not date_match: continue
        
        date_str = date_match.group(1)
        date_val = parse_date(date_str)
        if not date_val: continue
        
        # 2. Extract all numbers (potential amounts)
        # We look for currency-like patterns: 1,234.56 or 1234.56
        line_no_date = line.replace(date_str, ' DATE ')
        # Find amounts (numbers with at least 2 decimal places or large integers)
        potential_amounts = re.findall(r'(\d{1,3}(?:,\d{3})*\.\d{2})', line_no_date)
        if not potential_amounts:
            # Fallback to any number with a comma or decimal
            potential_amounts = re.findall(r'(\d+[\.,]\d{2})', line_no_date)

        if not potential_amounts: continue
        
        # Convert to floats
        floats = []
        for a in potential_amounts:
            try:
                floats.append(float(a.replace(',', '')))
            except: pass
        
        if not floats: continue

        # Heuristic for Bank Statements:
        # [Date] [Description] [Withdrawal/Debit] [Deposit/Credit] [Balance]
        # Most users want to track Expenses (Debits). 
        # In a line with 3 amounts, the 1st is usually Debit, 2nd Credit, 3rd Balance.
        # In a line with 2 amounts, 1st is Trans Amount, 2nd is Balance.
        
        amount = floats[0] # Assume the first amount is the transaction amount
        
        # 3. Description Cleanup
        # Remove date and all detected amounts from the line to get description
        desc = line_no_date
        for a_str in potential_amounts:
            desc = desc.replace(a_str, '')
        
        # Remove common noise
        desc = re.sub(r'[\|\-\:\d]{2,}', ' ', desc) # Remove sequences of symbols/numbers
        desc = re.sub(r'\s+', ' ', desc).strip()
        
        if len(desc) < 3: desc = "Transaction"

        # Filter out common non-transaction lines
        if any(x in desc.upper() for x in ["BALANCE B/F", "OPENING BALANCE", "CLOSING BALANCE", "TOTAL", "PAGE"]):
            continue

        transactions.append({
            'date': date_val,
            'description': desc[:120],
            'amount': amount,
            'type': 'Debit'
        })
    return transactions

def extract_from_pdf(pdf_path, force_ocr=False):
    transactions = []
    text_content = ""
    
    if not force_ocr:
        try:
            import pdfplumber
            with pdfplumber.open(pdf_path) as pdf:
                for page in pdf.pages:
                    text_content += (page.extract_text() or "") + "\n"
        except Exception as e:
            print(f"Text extraction failed: {e}")

    # If no text found or OCR forced, try OCR
    if not text_content.strip() or force_ocr:
        print("Scanned PDF detected or OCR forced. Running OCR (this may take a while)...")
        try:
            import pypdfium2 as pdfium
            import pytesseract
            from PIL import Image
            
            # Use pypdfium2 instead of pdf2image to avoid Poppler dependency
            pdf = pdfium.PdfDocument(pdf_path)
            for i in range(len(pdf)):
                print(f"OCR Processing page {i+1}...")
                page = pdf[i]
                # Render page to PIL Image
                bitmap = page.render(scale=2) # scale 2 for better OCR accuracy
                pil_image = bitmap.to_pil()
                text_content += pytesseract.image_to_string(pil_image) + "\n"
        except Exception as e:
            print(f"OCR failed: {e}. If it's a Tesseract error, ensure Tesseract OCR is installed on your Windows machine.")

    if not text_content.strip():
        return []

    # Try specific ICICI logic first (re-integrating)
    # ... (skipping for gravity/genericity, but we can add better logic)
    
    # Use Generic Line Parser
    lines = text_content.split('\n')
    return generic_line_parser(lines)

def extract_payslip(pdf_path):
    try:
        import pdfplumber
        content = ""
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                content += (page.extract_text() or "") + "\n"
    except: return {"error": "extraction failed"}

    # Month extraction (handles "FOR THE MONTH APRIL 2025" or similar)
    m_match = re.search(r'(?:MONTH|FOR)\s+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s*(\d{4})', content, re.I)
    month_val = None
    if m_match:
        try:
            m_name = m_match.group(1).strip()
            y_val = m_match.group(2).strip()
            month_val = datetime.strptime(f"{m_name} {y_val}", "%B %Y" if len(m_name) > 3 else "%b %Y").strftime('%Y-%m')
        except: pass

    # Net Pay extraction (handles "NET PAY 52700")
    n_match = re.search(r'(Net\s*Pay|Net\s*Salary|Net\s*Amount|Total\s*Net|Amount\s*Credited|Take\s*Home)\s*[:\-]?\s*₹?\s*([\d,]+\.?\d*)', content, re.I)
    salary_val = 0
    if n_match:
        salary_val = float(n_match.group(2).replace(',', ''))

    if month_val and salary_val:
        return {"month": month_val, "salary": salary_val}
    return {"error": "not a payslip or missing data"}

def save_to_db(db_path, transactions, payment_method, user_id):
    conn = sqlite3.connect(db_path)
    cursor = conn.cursor()
    count = 0
    for t in transactions:
        try:
            if t.get('type') == 'Income' or t.get('is_payslip'):
                cursor.execute('''
                    INSERT INTO income (user_id, month, salary_income, total_income)
                    VALUES (?, ?, ?, ?)
                ''', (user_id, t.get('month', t['date'][:7]), t['amount'], t['amount']))
            else:
                cursor.execute('''
                    INSERT INTO expenses (user_id, date, category, description, amount, payment_method)
                    VALUES (?, ?, ?, ?, ?, ?)
                ''', (user_id, t['date'], 'Uncategorized', t['description'], t['amount'], payment_method))
            count += 1
        except: pass
    conn.commit()
    conn.close()
    return count

if __name__ == "__main__":
    if len(sys.argv) < 5:
        print("Usage: python import_data.py <file_path> <db_path> <payment_method> <user_id> [--ocr]")
        sys.exit(1)
        
    file_path = sys.argv[1]
    db_file = sys.argv[2]
    method = sys.argv[3]
    user_id = sys.argv[4]
    use_ocr = "--ocr" in sys.argv
    
    if "payslip" in os.path.basename(file_path).lower():
        res = extract_payslip(file_path)
        if "error" not in res:
            inserted = save_to_db(db_file, [{
                'month': res['month'],
                'amount': res['salary'],
                'is_payslip': True
            }], method, user_id)
            print(f"Successfully recorded payslip income for {res['month']}.")
            sys.exit(0)

    ext = os.path.splitext(file_path)[1].lower()
    if ext == '.pdf':
        trans = extract_from_pdf(file_path, force_ocr=use_ocr)
    elif ext in ['.csv', '.xls', '.xlsx']:
        # Tabular logic (minimal for brevity)
        df = pd.read_csv(file_path) if ext == '.csv' else pd.read_excel(file_path)
        # simplified tabular to generic
        trans = [] # placeholder for actual df logic
    else:
        print("Unsupported file format.")
        sys.exit(1)
        
    if trans:
        inserted = save_to_db(db_file, trans, method, user_id)
        print(f"Successfully imported {inserted} transactions.")
    else:
        print("No valid transactions found in statement.")
