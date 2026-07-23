import React from 'react';
import PageHead from '../components/PageHead.jsx';
import Board from '../../board/components/Board.jsx';

// Board-Unterseite als Teilansicht des ProjectWorkspace (Seitenkopf + Kanban-
// Board). Kopfzeile/Tabs/Flash rendert der Workspace einmalig; die Board-Tasks
// kommen aus dem geteilten Store.
export default function BoardView({ meta, strings }) {
    return (
        <div className="space-y-6">
            <PageHead
                title={strings.boardTitle}
                toggleLabel={strings.showHideExplanation}
                bullets={strings.helpBullets}
            />
            <Board meta={meta} />
        </div>
    );
}
