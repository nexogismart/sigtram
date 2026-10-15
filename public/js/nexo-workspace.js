(() => {
    "use strict";
    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => [...document.querySelectorAll(selector)];
    const data = JSON.parse($("#workspaceData").textContent);
    data.areaAliases = JSON.parse(document.body.dataset.areaAliases || "{}");
    const normalize = (text) =>
        String(text || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();
    function safeUrl(url) {
        if (!url || url === "#") return null;
        try {
            const parsed = new URL(url, location.href);
            return ["http:", "https:"].includes(parsed.protocol)
                ? parsed.href
                : null;
        } catch (_) {
            return null;
        }
    }
    const modules = data.menu
        .filter((group) => group.workspace?.active !== false)
        .flatMap((group) =>
            (group.children?.length ? group.children : [group])
                .filter((entry) => entry.workspace?.active !== false)
                .map((entry) => ({
                    label: entry.workspace?.actionLabel || entry.label,
                    group: entry.workspace?.service || group.label,
                    sourceGroup: group.label,
                    sourceId: Number(group.id),
                    sourceLabel: entry.label,
                    space: entry.workspace?.space || "otros",
                    badge: entry.workspace?.badge || "",
                    owners: entry.workspace?.owners || [],
                    sharedWith: entry.workspace?.sharedWith || [],
                    audience:
                        entry.workspace?.audience ||
                        "Acceso individual asignado",
                    url: safeUrl(entry.url),
                    popup: Boolean(entry.popup),
                })),
        )
        .filter((entry) => entry.url);
    const keyFor = (entry) =>
        `${entry.sourceGroup}|${entry.sourceLabel}|${entry.url}`;
    const moduleByKey = new Map(modules.map((entry) => [keyFor(entry), entry]));
    const spaceDetails = {
        ...data.spaces,
        otros: {
            label: "Otros servicios",
            icon: "shapes",
            description: "Accesos pendientes de clasificación.",
        },
    };
    const groups = Object.keys(spaceDetails).filter((key) =>
        modules.some((entry) => entry.space === key),
    );
    const searchText = (entry) =>
        normalize(
            `${entry.label} ${entry.sourceLabel} ${entry.group} ${entry.sourceGroup} ${entry.badge} ${entry.owners.join(" ")} ${spaceDetails[entry.space]?.label || ""}`,
        );
    let catalogScope = "";
    function isHelpdesk(entry) {
        return [134,161].includes(entry.sourceId) || /^mesa de ayuda(?: 2)?$/.test(normalize(entry.sourceGroup)) || /\/mod_ti\/helpdesk(?:\/|$)/i.test(new URL(entry.url).pathname);
    }
    function areaKey(value) {
        return normalize(data.areaAliases?.[normalize(value)] || value);
    }
    function groupModuleEntries(entries) {
        const grouped = new Map();
        entries.forEach(entry => {
            const key = entry.sourceId > 0 ? String(entry.sourceId) : entry.sourceGroup;
            if (!grouped.has(key)) grouped.set(key, {key, label: entry.sourceGroup, entries: []});
            grouped.get(key).entries.push(entry);
        });
        return [...grouped.values()];
    }
    function recentDateGroup(at, now = Date.now()) {
        const format = new Intl.DateTimeFormat('en-CA', {timeZone:'America/Lima', year:'numeric', month:'2-digit', day:'2-digit'});
        const dayKey = value => {
            const parts = Object.fromEntries(format.formatToParts(new Date(value)).map(part => [part.type, part.value]));
            return `${parts.year}-${parts.month}-${parts.day}`;
        };
        const key = dayKey(at);
        const label = key === dayKey(now) ? "Hoy" : key === dayKey(now - 86400000) ? "Ayer"
            : new Intl.DateTimeFormat('es-PE', {timeZone:'America/Lima', day:'numeric', month:'long', year:'numeric'}).format(new Date(at));
        return {key, label};
    }
    function scopeFor(entry) {
        if (isHelpdesk(entry)) return "helpdesk";
        const activeArea = areaKey(data.context.area);
        const allAreas = areaKey("Todas las áreas orgánicas");
        // Ownership takes priority: a transversal service is still part of its managing area.
        if (activeArea && entry.owners.some(owner => areaKey(owner) === activeArea)) return "area";
        if (entry.owners.some(owner => areaKey(owner) === allAreas) || entry.sharedWith.some(area => areaKey(area) === allAreas)) return "shared";
        return "assigned";
    }

    function readStorage(suffix) {
        try {
            const value = JSON.parse(
                localStorage.getItem(`${data.storageKey}.${suffix}`),
            );
            return Array.isArray(value) ? value : [];
        } catch (_) {
            return [];
        }
    }
    const favorites = new Set(
        readStorage("favorites").filter((key) => moduleByKey.has(key)),
    );
    let recent = readStorage("recent")
        .filter(
            (item) =>
                item &&
                moduleByKey.has(item.key) &&
                Number.isFinite(item.at) &&
                !Number.isNaN(new Date(item.at).getTime()),
        )
        .slice(0, 12);
    let view = "groups";
    let selectedGroup = "";
    let limit = 12;
    let toastTimer;
    let boardView = "all";
    let boardGroup = "";
    let boardLimit = 6;
    let homePresented = false;
    const moduleMenuState = new Map();
    const explorer = $("#explorerDialog");
    function element(tag, className, text) {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== undefined) node.textContent = text;
        return node;
    }
    function icon(name) {
        const node = element("i", `fa-solid fa-${name}`);
        node.setAttribute("aria-hidden", "true");
        return node;
    }
    function notify(message) {
        clearTimeout(toastTimer);
        (explorer.open ? explorer : document.body).append($("#workspaceToast"));
        $("#workspaceToast").textContent = message;
        $("#workspaceToast").hidden = false;
        toastTimer = setTimeout(() => {
            $("#workspaceToast").hidden = true;
        }, 3200);
    }
    function persist(suffix, value) {
        try {
            localStorage.setItem(
                `${data.storageKey}.${suffix}`,
                JSON.stringify(value),
            );
        } catch (_) {
            notify(
                "El navegador no permite guardar estos accesos. Se conservarán durante esta visita.",
            );
        }
    }
    function moduleIcon(entry) {
        const label = normalize(`${entry.group} ${entry.label}`);
        if (/reporte|estadistic/.test(label)) return "chart-simple";
        if (/usuario|personal|ciudadano/.test(label)) return "users";
        if (/correo|envio|deriv/.test(label)) return "paper-plane";
        if (/consulta|busca|informacion/.test(label)) return "magnifying-glass";
        if (/document|tramite|expediente/.test(label)) return "file-lines";
        if (/config|parametro|seguridad/.test(label)) return "sliders";
        return "layer-group";
    }
    function makeLink(entry) {
        const link = element("a");
        link.href = entry.url;
        link.dataset.moduleKey = keyFor(entry);
        if (entry.popup || new URL(entry.url).origin !== location.origin) {
            link.target = "_blank";
            link.rel = "noopener";
        }
        return link;
    }
    function emptyState(title, description, symbol) {
        const node = element("div", "empty-state");
        node.append(
            icon(symbol || "magnifying-glass"),
            element("h3", "", title),
            element("p", "", description),
        );
        return node;
    }
    function favoriteButton(entry) {
        const key = keyFor(entry);
        const button = element("button", "favorite-button");
        button.type = "button";
        button.dataset.favoriteKey = key;
        button.setAttribute("aria-pressed", String(favorites.has(key)));
        button.setAttribute(
            "aria-label",
            `${favorites.has(key) ? "Quitar de" : "Añadir a"} favoritos: ${entry.label} (${entry.group}${entry.badge ? " · " + entry.badge : ""})`,
        );
        button.title = button.getAttribute("aria-label");
        const star = icon("star");
        if (!favorites.has(key)) star.className = "fa-regular fa-star";
        button.append(star);
        return button;
    }
    function moduleCard(entry, compact = false) {
        const card = element(
            "article",
            compact ? "module-card compact" : "module-card",
        );
        const link = makeLink(entry);
        const tileIcon = element("span", "module-icon");
        tileIcon.append(icon(moduleIcon(entry)));
        const copy = element("span", "module-copy");
        copy.append(
            element("strong", "", entry.label),
            element(
                "span",
                "",
                `${entry.group} · ${spaceDetails[entry.space].label}${entry.badge ? " · " + entry.badge : ""}`,
            ),
        );
        const qualifier = entry.badge || (modules.some(other => other.group === entry.group && other.label === entry.label && other.sourceGroup !== entry.sourceGroup) ? entry.sourceGroup : "");
        if (qualifier) copy.append(element("small", "action-qualifier", qualifier));
        link.append(tileIcon, copy, icon(entry.popup || new URL(entry.url).origin !== location.origin ? "arrow-up-right-from-square" : "arrow-right"));
        card.append(link, favoriteButton(entry));
        return card;
    }
    function rankedGroups() {
        const preferred = [...favorites, ...recent.map((item) => item.key)].map(
            (key) => moduleByKey.get(key)?.space,
        );
        return [...groups].sort((a, b) => {
            const rankA = preferred.indexOf(a),
                rankB = preferred.indexOf(b);
            return (rankA === -1 ? 999 : rankA) - (rankB === -1 ? 999 : rankB);
        });
    }
    function spaceCard(group, mini = false) {
        const entries = modules.filter((entry) => entry.space === group);
        const details = spaceDetails[group];
        const node = element(
            "button",
            mini ? "space-card mini" : "space-card workspace-space",
        );
        node.type = "button";
        node.dataset.group = group;
        const symbol = element("span", "space-icon");
        symbol.append(icon(details.icon));
        const copy = element("span", "space-copy");
        copy.append(
            element("strong", "", details.label),
            element("span", "", details.description),
        );
        const count = new Set(entries.map((entry) => entry.group)).size;
        copy.append(
            element(
                "small",
                "",
                `${count} ${count === 1 ? "servicio disponible" : "servicios disponibles"}`,
            ),
        );
        node.append(symbol, copy, icon("arrow-right"));
        return node;
    }
    function renderHelpdesk() {
        const target = $("#helpdeskLinks");
        target.replaceChildren();
        const entries = modules.filter(isHelpdesk);
        entries.forEach(entry => {
            const link = makeLink(entry);
            link.className = "helpdesk-link";
            const copy = element("span", "helpdesk-link-copy");
            copy.append(element("strong", "", entry.label), element("small", "", entry.group));
            link.append(icon("headset"), copy, icon(entry.popup ? "arrow-up-right-from-square" : "arrow-right"));
            target.append(link);
        });
        if (!entries.length) target.append(element("p", "helpdesk-empty", "No tienes opciones de Mesa de Ayuda asignadas a este cargo."));
    }
    function renderHome() {
        renderHelpdesk();
        renderResume();
        $("#favoriteCount").textContent = favorites.size;
        $("#favoriteCount").hidden = !favorites.size;
        renderBoard();
    }
    function renderResume() {
        const target = $("#workspaceResume");
        const entry = recent.map(item => moduleByKey.get(item.key)).find(item => item && !isHelpdesk(item));
        target.replaceChildren();
        target.hidden = !entry;
        $("#workspaceIntroDescription").hidden = Boolean(entry);
        if (!entry) return;
        const link = makeLink(entry);
        link.append(element("strong", "", entry.label), icon(entry.popup || new URL(entry.url).origin !== location.origin ? "arrow-up-right-from-square" : "arrow-right"));
        target.append(icon("clock-rotate-left"), element("span", "", "Retomar"), link, element("small", "", entry.group));
    }
    function renderFavorites(target, entries, query) {
        target.className = "module-board favorites-board";
        $("#boardTitle").textContent = "Tus favoritos";
        $("#boardDescription").textContent = "Tus accesos habituales, organizados por módulo.";
        $("#boardCount").textContent = `${entries.length} ${entries.length === 1 ? "acceso" : "accesos"}`;
        const toolbar = element("div", "favorites-toolbar");
        toolbar.append(element("p", "", "La estrella guarda o retira un acceso de este espacio."));
        const add = element("button", "favorites-add"); add.type = "button"; add.dataset.explore = "all";
        add.append(icon("plus"), element("span", "", "Añadir favoritos")); toolbar.append(add); target.append(toolbar);
        const totals = new Map(groupModuleEntries(entries).map(group => [group.key, group.entries.length]));
        groupModuleEntries(entries.slice(0, boardLimit * 2)).forEach(group => {
            const section = element("section", "favorites-module");
            const heading = element("header", "favorites-module-heading");
            heading.append(icon("folder-open"), element("h3", "", group.label), element("span", "", `${group.entries.length} de ${totals.get(group.key)} ${totals.get(group.key) === 1 ? "guardado" : "guardados"}`));
            const grid = element("div", "favorites-grid");
            group.entries.forEach(entry => {
                const card = element("article", "favorite-access");
                const link = makeLink(entry);
                link.setAttribute("aria-label", `Abrir ${entry.label} — ${entry.sourceGroup}`);
                const symbol = element("span", "favorite-access-icon"); symbol.append(icon(moduleIcon(entry)));
                const copy = element("span", "favorite-access-copy");
                copy.append(element("strong", "", entry.label), element("small", "", entry.badge ? `${entry.group} · ${entry.badge}` : entry.group));
                const open = element("span", "favorite-access-open");
                open.append(element("span", "", "Abrir"), icon(link.target === "_blank" ? "arrow-up-right-from-square" : "arrow-right"));
                link.append(symbol, copy, open);
                const remove = favoriteButton(entry); remove.append(element("span", "", "Quitar"));
                card.append(link, remove); grid.append(card);
            });
            section.append(heading, grid); target.append(section);
        });
        if (!entries.length) target.append(emptyState(query ? "No encontramos favoritos con ese nombre" : "Tu trabajo habitual, a un clic", query ? "Prueba otro nombre o limpia la búsqueda para ver tus accesos guardados." : "Usa Añadir favoritos y marca la estrella junto a las opciones que utilizas.", "star"));
        $("#showMoreBoard").hidden = entries.length <= boardLimit * 2;
        $("#showMoreBoard").firstChild.textContent = "Ver más favoritos ";
    }
    function renderRecent(target, entries, query) {
        target.className = "module-board recent-board";
        $("#boardTitle").textContent = "Actividad reciente";
        $("#boardDescription").textContent = "Tus últimos accesos en este navegador, del más reciente al anterior.";
        $("#boardCount").textContent = `${entries.length} ${entries.length === 1 ? "acceso" : "accesos"}`;
        const exploreButton = () => {
            const button = element("button", "favorites-add"); button.type = "button"; button.dataset.explore = "all";
            button.append(icon("folder-open"), element("span", "", "Explorar herramientas")); return button;
        };
        if (!entries.length) {
            const empty = element("section", "recent-empty");
            const copy = element("div", "recent-empty-copy");
            const symbol = element("span", "recent-empty-symbol"); symbol.append(icon("clock-rotate-left"));
            copy.append(symbol, element("h3", "", query ? "No hay accesos recientes con ese nombre" : "Retoma tu trabajo desde aquí"),
                element("p", "", query ? "Prueba otro nombre o limpia la búsqueda para ver tu historial." : "Al abrir una herramienta, aparecerá en este espacio para que puedas volver a ella directamente."));
            copy.append(exploreButton());
            const guide = element("div", "recent-empty-guide");
            [["folder-open","Elige un módulo"],["arrow-up-right-from-square","Abre una opción"],["clock-rotate-left","Encuéntrala en Recientes"]].forEach(([symbol, text]) => {
                const row = element("p", ""); row.append(icon(symbol), element("span", "", text)); guide.append(row);
            });
            empty.append(copy, guide); target.append(empty);
        } else {
            const toolbar = element("div", "favorites-toolbar");
            toolbar.append(element("p", "", "Se actualiza cuando abres una herramienta. Puedes guardar cualquiera en Favoritos."), exploreButton()); target.append(toolbar);
            const times = new Map(recent.map(item => [item.key, item.at]));
            const groups = new Map();
            entries.slice(0, boardLimit * 2).forEach(entry => {
                const at = times.get(keyFor(entry));
                const date = recentDateGroup(at);
                if (!groups.has(date.key)) groups.set(date.key, {...date, entries: []});
                groups.get(date.key).entries.push({entry, at});
            });
            groups.forEach(group => {
                const section = element("section", "recent-day");
                const heading = element("header", "recent-day-heading");
                heading.append(element("h3", "", group.label), element("span", "", `${group.entries.length} ${group.entries.length === 1 ? "acceso" : "accesos"}`));
                const list = element("div", "recent-list");
                group.entries.forEach(({entry, at}) => {
                    const latest = keyFor(entry) === recent[0]?.key;
                    const row = element("article", `recent-row${latest ? " latest" : ""}`);
                    const link = makeLink(entry); link.setAttribute("aria-label", `Retomar ${entry.label} — ${entry.sourceGroup}`);
                    const symbol = element("span", "recent-row-symbol"); symbol.append(icon(moduleIcon(entry)));
                    const copy = element("span", "recent-row-copy");
                    copy.append(element("strong", "", entry.label), element("small", "", entry.sourceGroup + (entry.badge ? ` · ${entry.badge}` : "")));
                    link.append(symbol, copy, icon(link.target === "_blank" ? "arrow-up-right-from-square" : "arrow-right"));
                    const stamp = element("div", "recent-row-stamp");
                    if (latest) stamp.append(element("span", "recent-latest-label", "Último acceso"));
                    const time = element("time", "", new Intl.DateTimeFormat('es-PE', {timeZone:'America/Lima', hour:'2-digit', minute:'2-digit', hour12:true}).format(new Date(at)));
                    time.dateTime = new Date(at).toISOString();
                    time.title = new Intl.DateTimeFormat('es-PE', {timeZone:'America/Lima',dateStyle:'full',timeStyle:'short'}).format(new Date(at));
                    stamp.append(time); row.append(link, stamp, favoriteButton(entry)); list.append(row);
                });
                section.append(heading, list); target.append(section);
            });
        }
        target.append(element("p", "recent-storage-note", "Historial de este navegador, asociado a tu usuario y cargo."));
        $("#showMoreBoard").hidden = entries.length <= boardLimit * 2;
        $("#showMoreBoard").firstChild.textContent = "Ver más recientes ";
    }
    function renderBoard() {
        const query = normalize($("#boardSearch").value);
        const target = $("#moduleBoard");
        target.querySelectorAll('[data-menu-key]').forEach(menu => moduleMenuState.set(menu.dataset.menuKey, menu.open));
        target.replaceChildren();
        $("#clearSearch").hidden = !query;
        const favoriteView = boardView === "favorites";
        const recentView = boardView === "recent";
        $("#boardSearch").placeholder = favoriteView ? "Buscar en mis favoritos" : recentView ? "Buscar en mis recientes" : "Buscar trámite, servicio o acción";
        $("#boardSearch").setAttribute("aria-label", favoriteView ? "Buscar en mis favoritos" : recentView ? "Buscar en mis recientes" : "Buscar un servicio o una acción");
        $(".board-heading").hidden = !query && boardView === "all";
        $$(".primary-navigation .dock-item").forEach(button => {
            const active = (button.hasAttribute("data-home") ? "all" : button.dataset.homeView) === boardView;
            button.classList.toggle("active", active);
            if (active) button.setAttribute("aria-current", "page");
            else button.removeAttribute("aria-current");
        });
        const results = Boolean(query) || boardView !== "all";
        target.className = results ? "module-board direct-results" : "module-board service-directory";
        if (results) {
            let entries = modules.filter(entry =>
                (boardView === "favorites" ? favorites.has(keyFor(entry)) : boardView === "recent" ? recent.some(item => item.key === keyFor(entry)) : true)
                && (!query || query.split(/\s+/).every(term => searchText(entry).includes(term))));
            if (favoriteView) { renderFavorites(target, entries, query); return; }
            if (recentView) {
                entries.sort((a,b) => recent.findIndex(item => item.key === keyFor(a)) - recent.findIndex(item => item.key === keyFor(b)));
                renderRecent(target, entries, query); return;
            }
            $("#boardTitle").textContent = query ? "Resultados" : boardView === "favorites" ? "Tus favoritos" : "Continúa donde estabas";
            $("#boardDescription").textContent = query ? "Abre directamente la acción que necesitas." : "Accesos directos a tu trabajo.";
            $("#boardCount").textContent = `${entries.length} ${entries.length === 1 ? "acceso" : "accesos"}`;
            entries.slice(0, boardLimit * 2).forEach(entry => target.append(moduleCard(entry)));
            if (!entries.length) target.append(emptyState(query ? "No encontramos coincidencias" : boardView === "favorites" ? "Guarda aquí lo que usas a diario" : "Tu trabajo reciente aparecerá aquí", query ? "Prueba con el nombre del servicio, acción o área." : boardView === "favorites" ? "Marca la estrella junto a una acción para tener un acceso directo." : "Abre un servicio para empezar."));
            $("#showMoreBoard").hidden = entries.length <= boardLimit * 2;
            $("#showMoreBoard").firstChild.textContent = "Ver más resultados ";
            return;
        }
        target.className = "module-board dual-scope";
        target.classList.toggle("workspace-reveal", !homePresented);
        homePresented = true;
        $("#boardTitle").textContent = "Mi espacio";
        $("#showMoreBoard").hidden = true;
        function ranked(entries, prioritizeWork = false) {
            return [...entries].sort((a,b) => {
                const score = entry => {
                    if (favorites.has(keyFor(entry))) return -100;
                    const recentIndex = recent.findIndex(item => item.key === keyFor(entry));
                    if (recentIndex >= 0) return recentIndex - 50;
                    if (!prioritizeWork) return 0;
                    const action = normalize(entry.label + " " + entry.sourceLabel);
                    if (/bandeja|corr\.?\s*(interno|externo)/.test(action)) return 0;
                    if (/ingresar|registrar|registro|ingreso/.test(action)) return 1;
                    if (/buscar|consult|seguimiento/.test(action)) return 2;
                    if (/adjuntar/.test(action)) return 3;
                    return 4;
                };
                return score(a)-score(b);
            });
        }
        function actionRow(entry, grouped = false) {
            const row = element("div", "scope-action");
            const link = makeLink(entry);
            const mark = element("span", "scope-action-icon"); mark.append(icon(moduleIcon(entry)));
            const copy = element("span", "scope-action-copy");
            copy.append(element("strong", "", entry.label));
            const qualifier = entry.badge || (modules.some(other => other.group === entry.group && other.label === entry.label && other.sourceGroup !== entry.sourceGroup) ? entry.sourceGroup : "");
            if (!grouped) copy.append(element("small", "", entry.group + (qualifier ? ` / ${qualifier}` : "")));
            if (grouped) link.setAttribute("aria-label", `${entry.label} — ${entry.sourceGroup}`);
            link.append(mark,copy,icon(entry.popup || new URL(entry.url).origin !== location.origin ? "arrow-up-right-from-square" : "arrow-right"));
            row.append(link,favoriteButton(entry));
            return row;
        }
        function moduleMenus(entries, scope, expanded = false, openCount = 2) {
            const grid = element("div", "module-menu-grid");
            groupModuleEntries(ranked(entries, expanded)).forEach((group, index) => {
                const menu = element("details", "module-menu");
                const stateKey = `${scope}:${group.key}`;
                menu.dataset.menuKey = stateKey;
                menu.open = moduleMenuState.has(stateKey) ? moduleMenuState.get(stateKey) : index < openCount;
                const summary = element("summary", "module-menu-heading");
                const symbol = element("span", "module-menu-icon"); symbol.append(icon("folder-open"));
                summary.append(symbol, element("strong", "", group.label), element("small", "", `${group.entries.length} ${group.entries.length === 1 ? "opción" : "opciones"}`), icon("chevron-down"));
                const options = element("div", "module-menu-options");
                const limit = expanded ? 4 : 3;
                group.entries.slice(0, limit).forEach(entry => options.append(actionRow(entry, true)));
                if (group.entries.length > limit) {
                    const more = element("details", "module-menu-more");
                    more.dataset.menuKey = `${stateKey}:more`;
                    more.open = moduleMenuState.get(more.dataset.menuKey) || false;
                    more.append(element("summary", "", `Ver ${group.entries.length - limit} opciones más`));
                    group.entries.slice(limit).forEach(entry => more.append(actionRow(entry, true)));
                    options.append(more);
                }
                menu.append(summary, options); grid.append(menu);
            });
            return grid;
        }
        function moreButton(scope,count,label) {
            const button = element("button", "scope-more"); button.type="button";button.dataset.scope=scope;
            button.append(element("span", "", label),element("small", "", `${count} ${count === 1 ? "acceso" : "accesos"}`),icon("arrow-right"));return button;
        }
        function panel(scope,label,title,description, expanded = false) {
            const entries = modules.filter(entry => scopeFor(entry) === scope);
            const section = element("section",`scope-panel scope-${scope}`);
            const head = element("header", "scope-header");
            const symbol = element("span", "scope-symbol");symbol.append(icon(scope === "area" ? "building" : "file-lines"));
            const copy = element("div", "scope-title");
            const labelRow = element("p", "scope-label");
            const serviceCount = groupModuleEntries(entries).length;
            labelRow.append(element("span", "", label), element("span", "scope-service-count", `${serviceCount} ${serviceCount === 1 ? "módulo" : "módulos"}`));
            copy.append(labelRow,element("h2", "",title),element("p", "scope-description",description));
            head.append(copy,symbol);section.append(head);
            section.append(element("p", "scope-role", expanded ? `${data.context.area} · ${data.context.cargo}` : scope === "area" ? data.context.cargo : "Servicios habilitados para tu cargo"));
            const list = element("div", "scope-list");
            if (entries.length) list.append(moduleMenus(entries, scope, expanded));
            if(!entries.length) list.append(element("div", "scope-empty",scope === "area" ? "No tienes acciones asignadas de esta área. Consulta tus demás accesos en Servicios compartidos." : "No tienes servicios compartidos asignados a este cargo."));
            section.append(list);
            if(entries.length) section.append(moreButton(scope,entries.length,scope === "area" ? "Ver accesos de mi área" : scope === "assigned" ? "Ver todos mis accesos" : "Ver servicios compartidos"));
            return section;
        }
        const areaTitle = modules.flatMap(entry => entry.owners).find(owner => areaKey(owner) === areaKey(data.context.area)) || data.context.area || "Área activa";
        const hasArea = modules.some(entry => scopeFor(entry) === "area");
        target.classList.toggle("single-scope", !hasArea);
        if (hasArea) target.append(panel("area","Mi área",areaTitle,"Herramientas asignadas para las funciones de tu área."));
        const assigned = modules.filter(entry => scopeFor(entry) === "assigned");
        const primaryScope = !hasArea && !modules.some(entry => scopeFor(entry) === "shared") && assigned.length ? "assigned" : "shared";
        const shared = panel(primaryScope,primaryScope === "assigned" ? "Tus herramientas" : "Servicios compartidos","Gestión administrativa","Trámites, documentos y servicios habilitados para tu trabajo.", !hasArea);
        shared.classList.add("scope-shared");
        if(assigned.length && primaryScope !== "assigned") {
            const other = element("details", "assigned-access");
            other.append(element("summary", "", `Otros accesos asignados (${assigned.length})`),element("p", "", "Servicios de otras áreas o pendientes de clasificación, habilitados para ti."));
            other.append(moduleMenus(assigned, "assigned", false, 0));
            other.append(moreButton("assigned",assigned.length,"Ver todos los asignados"));shared.append(other);
        }
        target.append(shared);
    }
    function filteredModules() {
        const query = normalize($("#explorerSearch").value);
        let list = modules.filter(
            (entry) =>
                (!catalogScope || scopeFor(entry) === catalogScope) &&
                (!spaceDetails[selectedGroup]?.label ||
                    entry.space === selectedGroup) &&
                (!query ||
                    query
                        .split(/\s+/)
                        .every((term) => searchText(entry).includes(term))) &&
                (view !== "favorites" || favorites.has(keyFor(entry))) &&
                (view !== "recent" ||
                    recent.some((item) => item.key === keyFor(entry))),
        );
        if (view === "recent")
            list.sort(
                (a, b) =>
                    recent.findIndex((item) => item.key === keyFor(a)) -
                    recent.findIndex((item) => item.key === keyFor(b)),
            );
        return list;
    }
    function renderExplorer() {
        const query = normalize($("#explorerSearch").value);
        const isGroups = view === "groups" && !selectedGroup && !query;
        const results = $("#explorerResults");
        results.replaceChildren();
        results.classList.toggle("group-results", isGroups);
        results.classList.toggle("module-results", !isGroups);
        $$("[data-view]").forEach((button) => {
            button.classList.toggle("active", button.dataset.view === view);
            button.setAttribute(
                "aria-pressed",
                String(button.dataset.view === view),
            );
        });
        $("#backToSpaces").hidden = !selectedGroup;
        $("#explorerLocation").textContent =
            spaceDetails[selectedGroup]?.label ||
            (query
                ? `Resultados para “${$("#explorerSearch").value.trim()}”`
                : {
                      groups: "Tus espacios",
                      all: "Todas las acciones",
                      favorites: "Tus favoritos",
                      recent: "Tus últimos accesos",
                  }[view]);
        if(catalogScope) $("#explorerLocation").textContent = {area:"Mi área",shared:"Servicios compartidos",assigned:"Otros accesos asignados"}[catalogScope];
        const list = isGroups ? rankedGroups() : filteredModules();
        $("#explorerCount").textContent =
            `${list.length} ${isGroups ? (list.length === 1 ? "espacio" : "espacios") : list.length === 1 ? "herramienta" : "herramientas"}`;
        if (isGroups) list.slice(0, limit).forEach(entry => results.append(spaceCard(entry)));
        else {
            const totals = new Map(groupModuleEntries(list).map(group => [group.key, group.entries.length]));
            groupModuleEntries(list.slice(0, limit)).forEach(group => {
                const section = element("section", "explorer-module");
                const header = element("header", "explorer-module-heading");
                header.append(element("h3", "", group.label), element("span", "", `${group.entries.length} de ${totals.get(group.key)} opciones`));
                const actions = element("div", "explorer-module-actions");
                group.entries.forEach(entry => actions.append(moduleCard(entry, true)));
                section.append(header, actions); results.append(section);
            });
        }
        if (!list.length) {
            let title = "No encontramos coincidencias",
                text =
                    "Prueba el nombre del espacio o una parte del nombre de la herramienta.";
            if (!modules.length) {
                title = "Aún no tienes herramientas asignadas";
                text =
                    "Consulta con tu administrador los accesos de este cargo.";
            } else if (view === "favorites" && !query) {
                title = "Guarda tus herramientas de gestión";
                text =
                    "Marca la estrella de una herramienta para tenerla siempre a mano.";
            } else if (view === "recent" && !query) {
                title = "Retoma tu gestión";
                text = "Los módulos que abras aparecerán en esta sección.";
            }
            results.append(
                emptyState(
                    title,
                    text,
                    view === "favorites" ? "star" : "magnifying-glass",
                ),
            );
        }
        $("#showMore").hidden = list.length <= limit;
    }
    function openExplorer(next = "groups", group = "") {
        view = next === "search" ? "all" : next;
        selectedGroup = group;
        limit = 12;
        $("#explorerSearch").value = "";
        renderExplorer();
        if (!explorer.open) explorer.showModal();
        $("#explorerSearch").focus();
        $("#profileMenu").open = false;
    }
    function showHome() {
        $("#dashboardHome").hidden = false;
        $("#modulePanel").hidden = true;
        $("#contentFrame").src = "about:blank";
        $("#currentPage").textContent = "Mi espacio";
        document.title = "SIGTRAM · Sistema de Gestión de Trámites";
        $(".dock-item[data-home]").classList.add("active");
        $(".dock-item[data-home]").setAttribute("aria-current", "page");
        window.scrollTo({ top: 0 });
        $("#mainContent").focus({ preventScroll: true });
    }
    function recordAccess(entry) {
        recent = [
            { key: keyFor(entry), at: Date.now() },
            ...recent.filter((item) => item.key !== keyFor(entry)),
        ].slice(0, 12);
        persist("recent", recent);
        renderHome();
    }
    function findAssignedModule(url) {
        const target = safeUrl(url);
        if (!target) return null;
        const parsed = new URL(target);
        if (parsed.origin !== location.origin) return null;
        return modules.find(entry => {
            const assigned = new URL(entry.url);
            return !entry.popup && assigned.origin === parsed.origin && assigned.pathname === parsed.pathname;
        }) || null;
    }
    function activateAssignedModule(entry, targetUrl = entry.url) {
        $("#dashboardHome").hidden = true;
        $("#modulePanel").hidden = false;
        $("#contentFrame").src = targetUrl;
        $("#contentFrame").title = entry.label;
        $("#moduleTitle").textContent =
            `${entry.group} / ${entry.label}${entry.badge ? " · " + entry.badge : ""}`;
        $("#currentPage").textContent = entry.label;
        $("#openModuleTab").href = targetUrl;
        $(".dock-item[data-home]").classList.remove("active");
        $(".dock-item[data-home]").removeAttribute("aria-current");
        document.title = `${entry.label}${entry.badge ? " · " + entry.badge : ""} · ${entry.group} · SIGTRAM`;
        window.scrollTo({ top: 0 });
        $("#moduleTitle").tabIndex = -1;
        $("#moduleTitle").focus({ preventScroll: true });
    }
    // Compatibility for the backend's iframe navigation. Only assigned menu destinations qualify.
    window.activarModuloPorUrl = url => {
        const entry = findAssignedModule(url);
        if (!entry) return false;
        recordAccess(entry);
        if (explorer.open) explorer.close();
        activateAssignedModule(entry, safeUrl(url));
        return true;
    };
    window.loadContentInPanel = url => {
        const opened = window.activarModuloPorUrl(url);
        if (!opened) notify("Este acceso no está disponible en tu menú asignado.");
        return opened;
    };
    document.addEventListener("click", (event) => {
        const favorite = event.target.closest("[data-favorite-key]");
        if (favorite) {
            const key = favorite.dataset.favoriteKey;
            const entry = moduleByKey.get(key);
            if (!entry) return;
            if (favorites.has(key)) favorites.delete(key);
            else favorites.add(key);
            persist("favorites", [...favorites]);
            const inExplorer = explorer.contains(favorite);
            const wasBoard = $("#moduleBoard").contains(favorite);
            renderHome();
            if (explorer.open) renderExplorer();
            const target = [
                ...(inExplorer
                    ? explorer
                    : wasBoard
                      ? $("#moduleBoard")
                      : $("#moduleBoard")
                ).querySelectorAll("[data-favorite-key]"),
            ].find((button) => button.dataset.favoriteKey === key);
            (
                target ||
                (inExplorer ? $("#explorerSearch") : $("#boardSearch"))
            )?.focus({ preventScroll: true });
            notify(
                favorites.has(key)
                    ? `${entry.label} guardado en tu inicio.`
                    : `${entry.label} retirado de favoritos.`,
            );
            return;
        }
        const boardTab = event.target.closest(
            "[data-board-view], [data-home-view]",
        );
        if (boardTab) {
            boardView = boardTab.dataset.boardView || boardTab.dataset.homeView;
            boardGroup = "";
            boardLimit = 6;
            $("#boardSearch").value = "";
            showHome();
            renderHome();
            $("#boardTitle").tabIndex = -1;
            $("#boardTitle").focus({ preventScroll: true });
            return;
        }
        const scoped = event.target.closest("[data-scope]");
        if(scoped) { catalogScope = scoped.dataset.scope; openExplorer("all"); return; }
        const explore = event.target.closest("[data-explore]");
        if (explore) {
            catalogScope = "";
            openExplorer(explore.dataset.explore);
            return;
        }
        const space = event.target.closest("[data-group]");
        if (space) {
            openExplorer("groups", space.dataset.group);
            return;
        }
        if (event.target.closest("[data-home]")) {
            event.preventDefault();
            boardView = "all";
            boardGroup = "";
            boardLimit = 6;
            $("#boardSearch").value = "";
            showHome();
            renderHome();
            return;
        }
        const link = event.target.closest("[data-module-key]");
        if (!link) return;
        const entry = moduleByKey.get(link.dataset.moduleKey);
        if (!entry) {
            event.preventDefault();
            return;
        }
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)
            return;
        $("#helpdeskMenu").open = false;
        recordAccess(entry);
        if (link.target === "_blank") {
            if (explorer.open) explorer.close();
            return;
        }
        event.preventDefault();
        if (explorer.open) explorer.close();
        activateAssignedModule(entry);
    });
    $("#closeExplorer").addEventListener("click", () => explorer.close());
    explorer.addEventListener("click", (event) => {
        if (event.target === explorer) {
            const bounds = explorer.getBoundingClientRect();
            if (
                event.clientX < bounds.left ||
                event.clientX > bounds.right ||
                event.clientY < bounds.top ||
                event.clientY > bounds.bottom
            )
                explorer.close();
        }
    });
    explorer.addEventListener("close", () =>
        document.body.classList.remove("explorer-open"),
    );
    new MutationObserver(() =>
        document.body.classList.toggle("explorer-open", explorer.open),
    ).observe(explorer, { attributes: true, attributeFilter: ["open"] });
    $$("[data-view]").forEach((button) =>
        button.addEventListener("click", () => {
            view = button.dataset.view;
            selectedGroup = "";
            limit = 12;
            $("#explorerSearch").value = "";
            renderExplorer();
        }),
    );
    $("#backToSpaces").addEventListener("click", () => {
        selectedGroup = "";
        limit = 12;
        renderExplorer();
    });
    $("#showMore").addEventListener("click", () => {
        limit += 12;
        renderExplorer();
    });
    $("#explorerSearch").addEventListener("input", () => {
        selectedGroup = "";
        limit = 12;
        if ($("#explorerSearch").value.trim()) view = "all";
        renderExplorer();
    });
    explorer.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            event.preventDefault();
            explorer.close();
            return;
        }
        const targets = [
            ...$("#explorerResults").querySelectorAll(
                ".space-card, .module-card > a",
            ),
        ];
        if (!targets.length) return;
        const index = targets.indexOf(document.activeElement);
        if (
            event.key === "ArrowDown" &&
            (document.activeElement === $("#explorerSearch") || index >= 0)
        ) {
            event.preventDefault();
            targets[Math.min(index + 1, targets.length - 1)].focus();
        }
        if (event.key === "ArrowUp" && index >= 0) {
            event.preventDefault();
            if (index === 0) $("#explorerSearch").focus();
            else targets[index - 1].focus();
        }
        if (
            event.key === "Enter" &&
            document.activeElement === $("#explorerSearch")
        ) {
            event.preventDefault();
            targets[0].click();
        }
    });
    document.addEventListener("keydown", (event) => {
        if (
            (event.ctrlKey || event.metaKey) &&
            event.key.toLowerCase() === "k" &&
            !$("#logoutDialog").open
        ) {
            event.preventDefault();
            showHome();
            $("#boardSearch").focus();
        }
        if (event.key === "Escape") {
            $("#profileMenu").open = false;
            $("#helpdeskMenu").open = false;
        }
    });
    document.addEventListener("click", (event) => {
        if (!$("#profileMenu").contains(event.target))
            $("#profileMenu").open = false;
        if (!$("#helpdeskMenu").contains(event.target))
            $("#helpdeskMenu").open = false;
    });
    function updateThemeButton() {
        const dark = document.documentElement.dataset.theme === "dark";
        $("#themeToggle").setAttribute(
            "aria-label",
            dark ? "Activar modo claro" : "Activar modo oscuro",
        );
        $("#themeToggle").replaceChildren(icon(dark ? "sun" : "moon"));
    }
    $("#themeToggle").addEventListener("click", () => {
        const next =
            document.documentElement.dataset.theme === "dark"
                ? "light"
                : "dark";
        document.documentElement.dataset.theme = next;
        try {
            localStorage.setItem("nexosigtram.theme", next);
        } catch (_) {}
        updateThemeButton();
    });
    const logout = $("#logoutDialog");
    $("#logoutButton").addEventListener("click", () => {
        $("#profileMenu").open = false;
        logout.showModal();
        $("#cancelLogout").focus();
    });
    $("#cancelLogout").addEventListener("click", () => logout.close());
    $("#confirmLogout").addEventListener("click", () => {
        $("#confirmLogout").disabled = true;
        $("#confirmLogout").textContent = "Cerrando sesión…";
        $("#logoutForm").submit();
    });
    const originalFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const response = await originalFetch(...args);
        if (response.status === 401) {
            notify(
                "Tu sesión ha finalizado. Vuelve a ingresar para continuar.",
            );
            setTimeout(() => location.assign("/login"), 1800);
        }
        return response;
    };
    $("#boardSearch").addEventListener("input", () => {
        boardLimit = 6;
        renderBoard();
    });
    $("#boardSearch").addEventListener("keydown", event => {
        if (event.key === "ArrowDown") {
            const first = $("#moduleBoard .module-card > a") || $("#moduleBoard .desktop-file > a");
            if (first) { event.preventDefault(); first.focus(); }
        }
        if (event.key === "Enter" && $("#boardSearch").value.trim()) {
            const first = $("#moduleBoard .module-card > a");
            if (first) { event.preventDefault(); first.click(); }
        }
    });
    $("#showMoreBoard").addEventListener("click", () => {
        boardLimit += 6;
        renderBoard();
        $("#boardTitle").tabIndex = -1;
        $("#boardTitle").focus({preventScroll:true});
    });
    $("#clearSearch").addEventListener("click", () => {
        $("#boardSearch").value = "";
        boardLimit = 6;
        renderBoard();
        $("#boardSearch").focus();
    });
    renderHome();
    updateThemeButton();
})();
(() => {
    'use strict';
    document.querySelectorAll('[data-profile-photo]').forEach(image => {
        const fallback = image.parentElement.querySelector('[data-avatar-initial]');
        const update = () => {
            const ready = image.complete && image.naturalWidth > 0;
            image.hidden = !ready;
            fallback.hidden = ready;
        };
        image.addEventListener('load', update);
        image.addEventListener('error', update);
        update();
    });
})();
