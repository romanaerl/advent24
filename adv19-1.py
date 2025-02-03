class Def:
    def read_input(self, file_path):
        with open(file_path, 'r') as file:
            lines = file.readlines()

        patterns = [pattern.strip() for pattern in lines.pop(0).split(',')]
        lines.pop(0)
        designs = [line.strip() for line in lines]

        print(f"Designs: {designs}")
        return patterns, list(filter(None, designs))

    def is_design_possible(self, design, patterns):
        final_candidates = {}
        cur_candidates = []
        design_arr = list(design)

        for i, cur_symb in enumerate(design_arr):
            cur_symb_has_any_candidates = False

            # Check old current candidates (iterate in reverse to avoid index issues)
            for key in range(len(cur_candidates) - 1, -1, -1):
                cand_arr, cand_start = cur_candidates[key]
                cand_i = i - cand_start
                if cand_arr[cand_i] == cur_symb:
                    cur_symb_has_any_candidates = True
                    if len(cand_arr) == cand_i + 1:
                        final_candidates.setdefault(cand_start, []).append((cand_start, len(cand_arr), cand_arr))
                        cur_candidates.pop(key)
                else:
                    cur_candidates.pop(key)

            # Check patterns for a good start
            for pattern in patterns:
                pattern_arr = list(pattern)
                if pattern_arr[0] == cur_symb:
                    if len(pattern_arr) == 1:
                        cur_symb_has_any_candidates = True
                        final_candidates.setdefault(i, []).append((i, 1, pattern_arr))
                    elif i < len(design_arr) - 1:
                        cur_symb_has_any_candidates = True
                        cur_candidates.append((pattern_arr, i))

            if not cur_symb_has_any_candidates:
                print(f"Design {design} FAILED")
                print(f"Patterns: {patterns}")
                print(f"Final Candidates: {final_candidates}")
                print(f"Current Candidates: {cur_candidates}")
                return 0

        print(f"Design: {design}")
        print(f"Final Candidates: {final_candidates}")

        return self.check_paths(0, design_arr, final_candidates)

    def check_paths(self, idx, design_arr, final_candidates, depth=0):
        total = 0
        depth += 1

        if idx in final_candidates:
            for cand in final_candidates[idx]:
                cand_len = cand[1]
                if cand_len + idx == len(design_arr):
                    total += 1
                else:
                    total += self.check_paths(idx + cand_len, design_arr, final_candidates, depth)

        return total

    def run(self):
        file_path = "data/19-1.example2"
        patterns, designs = self.read_input(file_path)
        found_count = 0

        for i, design in enumerate(designs):
            print(f"Processing design {design} <===================== {i}")
            count = self.is_design_possible(design, patterns)
            if count:
                found_count += count

        print(f"Found Count: {found_count}")

# Usage Example
def_instance = Def()
def_instance.run()
