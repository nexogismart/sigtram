"""Enforce AGENTS.md TEST-01 against an unmodified PHPUnit Clover report."""
import json
from pathlib import Path
import sys
import xml.etree.ElementTree as ET


def evaluate(report):
    groups = {
        'core': {'required': 100, 'statements': 0, 'covered': 0, 'files': 0},
        'business_services_and_repositories': {'required': 80, 'statements': 0, 'covered': 0, 'files': 0},
    }
    for file in report.iter('file'):
        name = file.attrib['name'].replace('\\', '/')
        group = 'core' if '/app/Services/Core/' in name else (
            'business_services_and_repositories' if '/app/Services/' in name or '/app/Repositories/' in name else None
        )
        if group is None:
            continue
        metrics = file.find('metrics')
        if metrics is None:
            raise ValueError(f'Missing file metrics: {name}')
        row = groups[group]
        row['files'] += 1
        row['statements'] += int(metrics.attrib['statements'])
        row['covered'] += int(metrics.attrib['coveredstatements'])
    for row in groups.values():
        row['percent'] = round(100 * row['covered'] / row['statements'], 2) if row['statements'] else 0
        row['passed'] = row['statements'] > 0 and row['covered'] * 100 >= row['required'] * row['statements']
    return groups


if __name__ == '__main__':
    if len(sys.argv) != 2:
        raise SystemExit('Usage: python scripts/quality/check-coverage.py coverage.xml')
    result = evaluate(ET.parse(Path(sys.argv[1])).getroot())
    print(json.dumps(result, indent=2))
    sys.exit(0 if all(row['passed'] for row in result.values()) else 1)
