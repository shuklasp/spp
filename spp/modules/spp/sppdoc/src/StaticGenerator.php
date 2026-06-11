<?php

namespace SPPMod\SPPDoc;

class StaticGenerator
{
    public static function build(string $outputDir)
    {
        $data = \SPPMod\SPPDoc\DocParser::parseCodebase(true);

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $indexHtml = self::renderIndex($data);
        file_put_contents($outputDir . '/index.html', $indexHtml);

        foreach ($data as $category => $classes) {
            foreach ($classes as $className => $classData) {
                $classHtml = self::renderClass($category, $className, $classData, $data);
                $filename = self::sanitizeFilename($className) . '.html';
                file_put_contents($outputDir . '/' . $filename, $classHtml);
                
                // Backwards compatibility/Alias for short names (e.g. SPPEvent.html)
                $shortName = basename(str_replace('\\', '/', $className));
                $shortFilename = $shortName . '.html';
                if ($shortFilename !== $filename && !file_exists($outputDir . '/' . $shortFilename)) {
                    file_put_contents($outputDir . '/' . $shortFilename, $classHtml);
                }
            }
        }
    }

    private static function getHeader($title = "SPP Documentation"): string
    {
        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>
        <style>
            /* Premium Glassmorphism UI */
            body { font-family: "Outfit", "JetBrains Mono", -apple-system, sans-serif; background: #0f172a; color: #f8fafc; margin: 0; display: flex; height: 100vh; }
            a { color: #38bdf8; text-decoration: none; }
            a:hover { text-decoration: underline; }
            
            .sidebar { width: 300px; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border-right: 1px solid rgba(255, 255, 255, 0.08); overflow-y: auto; padding: 1.5rem; display: flex; flex-direction: column; }
            .sidebar h2 { color: #f43f5e; font-size: 1.2rem; margin-bottom: 0.5rem; border-bottom: 1px solid rgba(255,255,255,0.03); padding-bottom: 0.5rem; }
            .sidebar::-webkit-scrollbar { width: 6px; }
            .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
            .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
            
            .content { flex: 1; padding: 3rem; overflow-y: auto; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); box-shadow: inset 4px 0 30px rgba(0, 0, 0, 0.1); }
            .content::-webkit-scrollbar { width: 6px; }
            .content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
            
            .class-header { border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 2rem; padding-bottom: 1rem; }
            .class-header h1 { margin: 0; color: #f8fafc; font-size: 2rem; }
            .namespace { color: #94a3b8; font-family: monospace; }
            
            .tree-category { font-weight: 600; padding: 10px 12px; color: var(--text-primary); font-size: 0.9rem; letter-spacing: 0.02em; border-bottom: 1px solid rgba(255,255,255,0.03); margin-top: 5px; cursor: pointer; transition: background 0.2s; border-radius: 6px; display: flex; align-items: center; }
            .tree-category:hover { background: rgba(255,255,255,0.05); }
            .tree-item { padding: 8px 12px; cursor: pointer; color: #94a3b8; font-size: 0.9rem; border-left: 3px solid transparent; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 0 6px 6px 0; margin: 2px 0; display: flex; align-items: center; }
            .tree-item:hover { background: rgba(255,255,255,0.03); color: #f8fafc; transform: translateX(2px); }
            .tree-item.active { background: linear-gradient(90deg, rgba(56, 189, 248, 0.15) 0%, transparent 100%); border-left-color: #38bdf8; font-weight: 600; color: #fff; }
            
            .docblock { background: rgba(0,0,0,0.2); padding: 20px; border-left: 4px solid #0ea5e9; margin: 20px 0; border-radius: 8px; font-family: "JetBrains Mono", monospace; white-space: pre-wrap; color: #cbd5e1; box-shadow: inset 0 2px 10px rgba(0,0,0,0.1); }
            .method { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); padding: 24px; border-radius: 12px; margin-bottom: 20px; transition: transform 0.2s, box-shadow 0.2s; }
            .method:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); border-color: rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); }
            .method h3 { margin: 0 0 12px 0; font-family: "JetBrains Mono", monospace; color: #f8fafc; font-size: 1.1rem; }
            .method-signature { color: #38bdf8; font-weight: normal; }
            
            .badge { display: inline-block; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; margin-right: 10px; letter-spacing: 0.05em; text-transform: uppercase; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .badge.public { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
            .badge.protected { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
            .badge.static { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; }
            .badge.inherited { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; }
        </style>
        <script>
            function toggleNode(el) {
                let path = el.getAttribute("data-path");
                let childrenDiv = el.nextElementSibling;
                let isExpanded = childrenDiv.style.display === "block";

                let parentPath = path.includes("\\\\") ? path.substring(0, path.lastIndexOf("\\\\")) : "";
                
                if (!isExpanded) {
                    document.querySelectorAll(".tree-category.expanded").forEach(node => {
                        let nodePath = node.getAttribute("data-path");
                        let nodeParentPath = nodePath.includes("\\\\") ? nodePath.substring(0, nodePath.lastIndexOf("\\\\")) : "";
                        
                        if (nodeParentPath === parentPath && nodePath !== path) {
                            node.classList.remove("expanded");
                            node.nextElementSibling.style.display = "none";
                            node.querySelector("span.icon").style.transform = "rotate(0deg)";
                        }
                    });
                    
                    el.classList.add("expanded");
                    childrenDiv.style.display = "block";
                    el.querySelector("span.icon").style.transform = "rotate(90deg)";
                } else {
                    el.classList.remove("expanded");
                    childrenDiv.style.display = "none";
                    el.querySelector("span.icon").style.transform = "rotate(0deg)";
                }
                
                let expandedCats = [];
                document.querySelectorAll(".tree-category.expanded").forEach(node => {
                    expandedCats.push(node.getAttribute("data-path"));
                });
                localStorage.setItem("sppdoc_expanded", JSON.stringify(expandedCats));
            }

            function searchDocs(query) {
                query = query.toLowerCase();
                let hasQuery = query.length > 0;
                
                document.querySelectorAll(".tree-item").forEach(item => {
                    if (!hasQuery || item.textContent.toLowerCase().includes(query)) {
                        item.style.display = "flex";
                        if (hasQuery) {
                            let parent = item.closest(".tree-children");
                            while (parent) {
                                parent.style.display = "block";
                                let cat = parent.previousElementSibling;
                                if (cat) {
                                    cat.style.display = "flex";
                                    cat.querySelector("span.icon").style.transform = "rotate(90deg)";
                                }
                                parent = parent.parentElement.closest(".tree-children");
                            }
                        }
                    } else {
                        item.style.display = "none";
                    }
                });

                if (!hasQuery) {
                    restoreState();
                } else {
                    document.querySelectorAll(".tree-children").forEach(childrenDiv => {
                        let hasVisibleItems = Array.from(childrenDiv.querySelectorAll(".tree-item")).some(i => i.style.display !== "none");
                        if (!hasVisibleItems && Array.from(childrenDiv.children).filter(c => c.classList.contains("tree-category") && c.style.display !== "none").length === 0) {
                            childrenDiv.style.display = "none";
                            if (childrenDiv.previousElementSibling) {
                                childrenDiv.previousElementSibling.style.display = "none";
                            }
                        }
                    });
                }
            }
            
            function restoreState() {
                document.querySelectorAll(".tree-children").forEach(el => el.style.display = "none");
                document.querySelectorAll(".tree-category").forEach(el => {
                    el.style.display = "flex";
                    el.classList.remove("expanded");
                    el.querySelector("span.icon").style.transform = "rotate(0deg)";
                });
                document.querySelectorAll(".tree-item").forEach(el => el.style.display = "flex");

                let saved = localStorage.getItem("sppdoc_expanded");
                if (saved) {
                    try {
                        let expandedCats = JSON.parse(saved);
                        expandedCats.forEach(path => {
                            let safePath = path.replace(/\\\\/g, "\\\\\\\\");
                            let cat = document.querySelector(".tree-category[data-path=\\"" + safePath + "\\"]");
                            if (cat) {
                                cat.classList.add("expanded");
                                cat.nextElementSibling.style.display = "block";
                                cat.querySelector("span.icon").style.transform = "rotate(90deg)";
                            }
                        });
                    } catch(e) {}
                }
            }
            
            document.addEventListener("DOMContentLoaded", () => {
                restoreState();
                let currentFile = window.location.pathname.split("/").pop();
                if (currentFile && currentFile !== "index.html") {
                    let activeClass = currentFile.replace(".html", "");
                    let activeItem = document.querySelector(".tree-item[data-class=\\"" + activeClass + "\\"]");
                    if (activeItem) {
                        activeItem.classList.add("active");
                        let parent = activeItem.closest(".tree-children");
                        while (parent) {
                            parent.style.display = "block";
                            let cat = parent.previousElementSibling;
                            if (cat) {
                                cat.classList.add("expanded");
                                cat.querySelector("span.icon").style.transform = "rotate(90deg)";
                            }
                            parent = parent.parentElement.closest(".tree-children");
                        }
                        setTimeout(() => activeItem.scrollIntoView({ behavior: "smooth", block: "center" }), 100);
                    }
                }
            });
        </script>
        </head><body>';
    }

    private static function buildNestedTree(array $data): array
    {
        $tree = ['name' => 'root', 'children' => [], 'classes' => []];
        
        foreach ($data as $namespace => $classes) {
            $parts = array_filter(explode('\\', $namespace));
            
            $current = &$tree;
            foreach ($parts as $part) {
                if (!isset($current['children'][$part])) {
                    $current['children'][$part] = ['name' => $part, 'children' => [], 'classes' => []];
                }
                $current = &$current['children'][$part];
            }
            
            foreach ($classes as $className => $classData) {
                $classData['_full_name'] = $className;
                $current['classes'][] = $classData;
            }
        }
        
        return self::trimEmpty($tree);
    }

    private static function trimEmpty(array $node): array
    {
        foreach ($node['children'] as $childName => $childNode) {
            $node['children'][$childName] = self::trimEmpty($childNode);
            if (empty($node['children'][$childName]['children']) && empty($node['children'][$childName]['classes'])) {
                unset($node['children'][$childName]);
            }
        }
        return $node;
    }

    private static function renderTreeNode(array $node, string $currentPath = ''): string
    {
        $html = '';
        
        $sortedChildrenNames = array_keys($node['children']);
        sort($sortedChildrenNames);
        foreach ($sortedChildrenNames as $childName) {
            $childNode = $node['children'][$childName];
            $childPath = $currentPath ? $currentPath . '\\' . $childName : $childName;
            
            $html .= '<div class="tree-category" data-path="' . htmlspecialchars($childPath) . '" onclick="toggleNode(this)">';
            $html .= '<span class="icon" style="margin-right: 5px; font-size: 0.8em; display: inline-block; transition: transform 0.2s;">▶</span>';
            $html .= '<span class="icon" style="margin-right: 5px; color: #facc15;">📁</span>';
            $html .= htmlspecialchars($childName);
            $html .= '</div>';
            
            $html .= '<div class="tree-children" data-parent="' . htmlspecialchars($childPath) . '" style="padding-left: 12px; border-left: 1px solid rgba(255,255,255,0.05); margin-left: 10px; display: none;">';
            $html .= self::renderTreeNode($childNode, $childPath);
            $html .= '</div>';
        }

        $classes = $node['classes'];
        usort($classes, function($a, $b) { return strcmp($a['name'], $b['name']); });
        foreach ($classes as $c) {
            $fullName = $c['_full_name'] ?? $c['name'];
            $file = self::sanitizeFilename($fullName) . '.html';
            $dataClass = self::sanitizeFilename($fullName);
            $icon = isset($c['type']) && $c['type'] === 'config' ? '⚙️' : '📄';
            $html .= '<div class="tree-item" data-class="' . htmlspecialchars($dataClass) . '" onclick="window.location.href=\'' . $file . '\'">';
            $html .= '<span class="icon" style="margin-right: 5px; opacity: 0.7;">' . $icon . '</span> ' . htmlspecialchars($c['name']);
            $html .= '</div>';
        }
        
        return $html;
    }

    private static function getSidebar(array $data): string
    {
        $html = '<div class="sidebar">';
        $html .= '<h2><a href="index.html" style="color:inherit;">SPP Documentation</a></h2>';
        $html .= '<input type="text" id="doc-search" placeholder="Search classes..." onkeyup="searchDocs(this.value)" style="width: 100%; box-sizing: border-box; padding: 10px 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-family: inherit; margin-bottom: 1rem; outline: none; transition: border-color 0.2s;">';
        
        $tree = self::buildNestedTree($data);
        $html .= '<div style="padding: 10px 0;">' . self::renderTreeNode($tree) . '</div>';
        
        $html .= '</div>';
        return $html;
    }

    private static function renderIndex(array $data): string
    {
        $html = self::getHeader("SPP Native Documentation");
        $html .= self::getSidebar($data);
        $html .= '<div class="content">';
        $html .= '<h1>SPP Native Documentation</h1>';
        $html .= '<p>Welcome to the SPP Framework Code Explorer. Select a class from the sidebar to view its structure, methods, and documentation blocks.</p>';
        $html .= '</div></body></html>';
        return $html;
    }

    private static function linkType(string $typeStr, array $allData): string
    {
        if (empty($typeStr)) return htmlspecialchars($typeStr);
        
        $types = explode('|', $typeStr);
        $linkedTypes = [];

        foreach ($types as $t) {
            $isNullable = str_starts_with($t, '?');
            $cleanType = ltrim($t, '?');
            
            $isArray = str_ends_with($cleanType, '[]');
            if ($isArray) {
                $cleanType = substr($cleanType, 0, -2);
            }

            $shortName = $cleanType;
            if (str_contains($cleanType, '\\')) {
                $parts = explode('\\', $cleanType);
                $shortName = end($parts);
            }

            $targetClass = null;
            foreach ($allData as $cat => $classes) {
                foreach ($classes as $clsName => $clsData) {
                    if ($clsName === $cleanType || $clsData['name'] === $shortName || '\\' . $clsName === $cleanType) {
                        $targetClass = $clsName;
                        break 2;
                    }
                }
            }

            $res = '';
            if ($isNullable) $res .= '?';

            if ($targetClass) {
                $file = self::sanitizeFilename($targetClass) . '.html';
                $res .= '<a href="' . $file . '" title="' . htmlspecialchars($cleanType) . '">' . htmlspecialchars($shortName) . '</a>';
            } else {
                $res .= htmlspecialchars($shortName);
            }
            
            if ($isArray) $res .= '[]';

            $linkedTypes[] = $res;
        }

        return implode('|', $linkedTypes);
    }

    private static function renderClass(string $category, string $className, array $classData, array $allData): string
    {
        $html = self::getHeader($classData['name'] . " - SPP Documentation");
        $html .= self::getSidebar($allData);
        $html .= '<div class="content">';
        
        if (isset($classData['type']) && $classData['type'] === 'config') {
            $html .= '<div class="class-header">';
            $html .= '<h1><span style="color:#94a3b8; font-size:1.2rem; font-weight:normal;">Configuration</span> ' . htmlspecialchars($classData['name']) . '</h1>';
            $html .= '<div style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 20px;">
                <i class="icon">⚙️</i> Configuration File
                <div style="margin-top: 5px;">File: ' . htmlspecialchars($classData['file']) . '</div>
            </div>';
            $html .= '</div>';
            $html .= '<div class="docblock" style="border-left-color: #f59e0b; font-family: \'JetBrains Mono\', monospace; font-size: 0.95rem; line-height: 1.5; background: rgba(15, 23, 42, 0.6); overflow-x: auto; white-space: pre-wrap;">' . htmlspecialchars($classData['content']) . '</div>';
            $html .= '</div></body></html>';
            return $html;
        }

        $html .= '<div class="class-header">';
        $html .= '<div class="namespace">' . htmlspecialchars($classData['namespace'] ?? '') . '</div>';
        $finalStr = !empty($classData['is_final']) ? 'final ' : '';
        $typeStr = !empty($classData['type']) ? $classData['type'] : 'class';
        $html .= '<h1><span style="color:#94a3b8; font-size:1.2rem; font-weight:normal;">' . $finalStr . $typeStr . '</span> ' . htmlspecialchars($classData['name']) . '</h1>';
        
        $inheritanceHtml = '';
        if (!empty($classData['parent'])) {
            $inheritanceHtml .= '<div><span style="color: #f43f5e;">extends</span> ' . self::linkType($classData['parent'], $allData) . '</div>';
        }
        if (!empty($classData['interfaces'])) {
            $interfaces = array_map(function($i) use ($allData) { return self::linkType($i, $allData); }, $classData['interfaces']);
            $inheritanceHtml .= '<div><span style="color: #f43f5e;">implements</span> ' . implode(', ', $interfaces) . '</div>';
        }
        if (!empty($classData['traits'])) {
            $traits = array_map(function($t) use ($allData) { return self::linkType($t, $allData); }, $classData['traits']);
            $inheritanceHtml .= '<div><span style="color: #f43f5e;">uses</span> ' . implode(', ', $traits) . '</div>';
        }
        if ($inheritanceHtml) {
            $html .= '<div style="font-family: monospace; font-size: 0.9rem; margin-top: 10px; color: #cbd5e1;">' . $inheritanceHtml . '</div>';
        }

        $html .= '<div style="color:#94a3b8; font-size: 0.9rem; margin-top: 10px;">File: ' . htmlspecialchars($classData['file']) . '</div>';
        $html .= '</div>';

        if (!empty($classData['docblock'])) {
            $html .= '<div class="docblock">' . htmlspecialchars($classData['docblock']) . '</div>';
        }

        if (!empty($classData['constants'])) {
            $html .= '<h2>Constants</h2>';
            foreach ($classData['constants'] as $const) {
                $html .= '<div class="method">';
                $html .= '<h3>';
                $html .= '<span class="badge ' . $const['visibility'] . '">' . $const['visibility'] . '</span>';
                if (!empty($const['inherited_from'])) $html .= '<span class="badge inherited" title="Inherited from ' . htmlspecialchars($const['inherited_from']) . '">inherited</span>';
                $html .= '<span class="method-signature" style="color: #38bdf8;">' . htmlspecialchars($const['name']) . '</span> = <span style="color: #a3e635;">' . htmlspecialchars($const['value']) . '</span>;';
                $html .= '</h3>';
                if (!empty($const['docblock'])) {
                    $html .= '<div class="docblock" style="background: #0f172a; border-left-color: #64748b;">' . htmlspecialchars($const['docblock']) . '</div>';
                }
                $html .= '</div>';
            }
        }

        if (!empty($classData['properties'])) {
            $html .= '<h2>Properties</h2>';
            foreach ($classData['properties'] as $prop) {
                $html .= '<div class="method">';
                $html .= '<h3>';
                $html .= '<span class="badge ' . $prop['visibility'] . '">' . $prop['visibility'] . '</span>';
                if (!empty($prop['static'])) $html .= '<span class="badge static">static</span>';
                if (!empty($prop['inherited_from'])) $html .= '<span class="badge inherited" title="Inherited from ' . htmlspecialchars($prop['inherited_from']) . '">inherited</span>';
                $html .= '<span style="color: #94a3b8; margin-right: 5px;">' . self::linkType($prop['type'] ?: 'mixed', $allData) . '</span><span class="method-signature" style="color: #38bdf8;">$' . htmlspecialchars($prop['name']) . '</span>';
                $html .= '</h3>';
                if (!empty($prop['docblock'])) {
                    $html .= '<div class="docblock" style="background: #0f172a; border-left-color: #64748b;">' . htmlspecialchars($prop['docblock']) . '</div>';
                }
                $html .= '</div>';
            }
        }

        if (!empty($classData['methods'])) {
            $html .= '<h2>Methods</h2>';
            foreach ($classData['methods'] as $method) {
                $html .= '<div class="method">';
                $html .= '<h3>';
                $html .= '<span class="badge ' . $method['visibility'] . '">' . $method['visibility'] . '</span>';
                if (!empty($method['static'])) $html .= '<span class="badge static">static</span>';
                if (!empty($method['inherited_from'])) $html .= '<span class="badge inherited" title="Inherited from ' . htmlspecialchars($method['inherited_from']) . '">inherited</span>';
                
                $params = [];
                foreach ($method['parameters'] as $p) {
                    $pt = $p['type'] ? self::linkType($p['type'], $allData) . ' ' : '';
                    $params[] = $pt . '$' . htmlspecialchars($p['name']) . ($p['optional'] ? ' = null' : '');
                }
                $sig = htmlspecialchars($method['name']) . '(' . implode(', ', $params) . '): ' . self::linkType($method['return_type'], $allData);
                
                $html .= '<span class="method-signature">' . $sig . '</span>';
                $html .= '</h3>';
                
                if (!empty($method['docblock'])) {
                    $html .= '<div class="docblock" style="background: #0f172a; border-left-color: #64748b;">' . htmlspecialchars($method['docblock']) . '</div>';
                }
                
                $html .= '</div>';
            }
        }

        $html .= '</div></body></html>';
        return $html;
    }

    private static function sanitizeFilename(string $className): string
    {
        return str_replace('\\', '_', $className);
    }
}
