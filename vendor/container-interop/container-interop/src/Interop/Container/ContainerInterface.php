<?php
/**
 * @license http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace Interop\Container;

use Interop\Container\Exception\ContainerException;
use Interop\Container\Exception\NotFoundException;

/**
 * Describes the interface of a container that exposes methods to read its entries.
 * 定义一个接口，该接口暴露方法以读取本实例
 */
interface ContainerInterface
{
    /**
     * Finds an entry of the container by its identifier and returns it.
     * 根据容器的唯一ID获取容器入口，并返回值
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundException  No entry was found for this identifier.
     * @throws ContainerException Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id);

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     * 如果容器能够通过给定ID获取入口，则返回true，否则返回false
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return boolean
     */
    public function has($id);
}
