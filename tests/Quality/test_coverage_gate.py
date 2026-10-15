import importlib.util
from pathlib import Path
import unittest
import xml.etree.ElementTree as ET


root = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location('coverage_gate', root / 'scripts/quality/check-coverage.py')
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)


def report(core, business):
    return ET.fromstring(f'''<coverage>
        <file name="C:/repo/app/Services/Core/Service.php">
            <metrics statements="10" coveredstatements="{core}"/>
        </file>
        <file name="/repo/app/Repositories/Repository.php">
            <metrics statements="10" coveredstatements="{business}"/>
        </file>
    </coverage>''')


class CoverageGateTest(unittest.TestCase):
    def test_exact_minimums_pass_on_windows_and_linux_paths(self):
        self.assertTrue(all(row['passed'] for row in gate.evaluate(report(10, 8)).values()))

    def test_core_below_100_fails_even_when_business_passes(self):
        self.assertFalse(gate.evaluate(report(9, 8))['core']['passed'])

    def test_business_below_80_fails_even_when_core_passes(self):
        self.assertFalse(gate.evaluate(report(10, 7))['business_services_and_repositories']['passed'])

    def test_missing_scopes_do_not_pass(self):
        self.assertFalse(any(row['passed'] for row in gate.evaluate(ET.fromstring('<coverage/>')).values()))


if __name__ == '__main__':
    unittest.main()
