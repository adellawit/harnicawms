/**
 * Task Kanban JS
 * WMS - Warehouse Management System
 * ============================================================================
 */

(function () {
    'use strict';

    // Initialize drag and drop
    const kanbanCards = document.querySelectorAll('.kanban-card');
    const kanbanColumns = document.querySelectorAll('.kanban-column-body');

    let draggedElement = null;

    // Add drag event listeners to cards
    kanbanCards.forEach(card => {
        card.addEventListener('dragstart', handleDragStart);
        card.addEventListener('dragend', handleDragEnd);
    });

    // Add drop event listeners to columns
    kanbanColumns.forEach(column => {
        column.addEventListener('dragover', handleDragOver);
        column.addEventListener('drop', handleDrop);
        column.addEventListener('dragenter', handleDragEnter);
        column.addEventListener('dragleave', handleDragLeave);
    });

    function handleDragStart(e) {
        draggedElement = this;
        this.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/html', this.outerHTML);
    }

    function handleDragEnd(e) {
        this.classList.remove('dragging');

        // Remove drag-over class from all columns
        kanbanColumns.forEach(column => {
            column.classList.remove('drag-over');
        });
    }

    function handleDragOver(e) {
        if (e.preventDefault) {
            e.preventDefault();
        }
        e.dataTransfer.dropEffect = 'move';
        return false;
    }

    function handleDragEnter(e) {
        this.classList.add('drag-over');
    }

    function handleDragLeave(e) {
        this.classList.remove('drag-over');
    }

    function handleDrop(e) {
        if (e.stopPropagation) {
            e.stopPropagation();
        }

        this.classList.remove('drag-over');

        if (draggedElement !== null) {
            // Get the status of the target column
            const targetStatus = this.getAttribute('data-status');
            const taskId = draggedElement.getAttribute('data-task-id');

            // Move the card to the new column
            this.appendChild(draggedElement);

            // Here you can add AJAX call to update task status in database
            console.log(`Task ${taskId} moved to ${targetStatus}`);

            // TODO: Add AJAX call to update task status
            // updateTaskStatus(taskId, targetStatus);
        }

        return false;
    }

    // Function to update task status (to be implemented)
    function updateTaskStatus(taskId, newStatus) {
        // TODO: Implement AJAX call to update task status
        // fetch(`/developer/task/update-status`, {
        //     method: 'POST',
        //     headers: {
        //         'Content-Type': 'application/json',
        //         'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        //     },
        //     body: JSON.stringify({
        //         task_id: taskId,
        //         status: newStatus
        //     })
        // })
        // .then(response => response.json())
        // .then(data => {
        //     console.log('Task status updated:', data);
        // })
        // .catch(error => {
        //     console.error('Error updating task status:', error);
        // });
    }

})();
