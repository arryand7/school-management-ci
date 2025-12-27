import argparse
import shutil
from pathlib import Path

import pandas as pd


def convert_excel(src_path, out_path, header=0):
    df = pd.read_excel(src_path, header=header)
    df.to_csv(out_path, index=False)


def main():
    parser = argparse.ArgumentParser(description="Convert legacy Excel files to CSV for import.")
    parser.add_argument("--src", required=True, help="Directory containing legacy Excel/CSV files.")
    parser.add_argument("--out", required=True, help="Output directory for converted CSV files.")
    args = parser.parse_args()

    src_dir = Path(args.src).expanduser().resolve()
    out_dir = Path(args.out).expanduser().resolve()
    out_dir.mkdir(parents=True, exist_ok=True)

    convert_excel(src_dir / "data_guru.xls", out_dir / "data_guru.csv")
    df_students = pd.read_excel(src_dir / "data_siswa.xlsx", dtype=str)
    df_students.to_csv(out_dir / "data_siswa.csv", index=False)
    df_ppdb = pd.read_excel(src_dir / "data_ppdb.xlsx", header=1, dtype=str)
    df_ppdb.to_csv(out_dir / "data_ppdb.csv", index=False)

    staff_email = src_dir / "staff_email.csv"
    student_email = src_dir / "student_email.csv"
    if staff_email.exists():
        shutil.copy2(staff_email, out_dir / "staff_email.csv")
    if student_email.exists():
        shutil.copy2(student_email, out_dir / "student_email.csv")


if __name__ == "__main__":
    main()
